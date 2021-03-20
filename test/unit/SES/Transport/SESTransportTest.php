<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\SwiftMailer\SES\Transport;

use Ingenerator\PHPUtils\Monitoring\ArrayMetricsAgent;
use Ingenerator\PHPUtils\Monitoring\OperationTimer;
use Ingenerator\SwiftMailer\Common\Transport\EmailDeliveryFailedException;
use Ingenerator\SwiftMailer\SES\Transport\SESTransport;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\Test\TestLogger;
use Swift_Events_EventDispatcher;
use Swift_Events_SendEvent;
use Swift_Events_SendListener;
use Swift_Events_SimpleEventDispatcher;
use Swift_Message;
use Swift_Mime_SimpleMessage;
use test\mock\Ingenerator\SwiftMailer\SES\Transport\SpyingSESClient;

class DummySwiftPlugin implements Swift_Events_SendListener
{
    protected array $got_events;

    public function assertGotEvents(array $expect)
    {
        Assert::assertSame($expect, $this->got_events);
    }

    public function beforeSendPerformed(Swift_Events_SendEvent $evt)
    {
        $this->got_events[] = [__FUNCTION__, $evt];
    }

    public function sendPerformed(Swift_Events_SendEvent $evt)
    {
        $this->got_events[] = [__FUNCTION__, $evt];
    }

}

class DummySwift_Message extends Swift_Message
{
    private string $dummy_mime_string;

    public static function withDummyMimeString(string $string): DummySwift_Message
    {
        $s                    = new self();
        $s->dummy_mime_string = $string;

        return $s;
    }

    public function toString(): string
    {
        return $this->dummy_mime_string;
    }
}

class SESTransportTest extends TestCase
{
    protected Swift_Events_EventDispatcher $event_dispatcher;

    protected SpyingSESClient $ses_client;

    protected ArrayMetricsAgent $metrics;

    protected TestLogger $log;

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(SESTransport::class, $this->newSubject());
    }

    public function test_it_is_started()
    {
        $this->assertTrue($this->newSubject()->isStarted());
    }

    public function test_it_throws_with_more_than_one_from_address()
    {
        $message = new Swift_Message;
        $message->addFrom('foo@bar.com');
        $message->addFrom('other@bar.com');
        $subject = $this->newSubject();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('multiple `from` address');
        $subject->send($message);
    }

    public function test_it_throws_with_more_than_one_to_address()
    {
        $message = new Swift_Message;
        $message->addTo('foo@bar.com');
        $message->addTo('other@bar.com');
        $subject = $this->newSubject();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('multiple `to` address');
        $subject->send($message);
    }

    public function test_its_register_plugin_just_binds_to_event_listener()
    {
        $plugin  = new DummySwiftPlugin;
        $subject = $this->newSubject();
        $subject->registerPlugin($plugin);

        $evt = $this->event_dispatcher->createSendEvent($subject, $this->getDummy(Swift_Mime_SimpleMessage::class));
        $this->event_dispatcher->dispatchEvent($evt, 'sendPerformed');
        $plugin->assertGotEvents([['sendPerformed', $evt]]);
    }

    public function test_its_send_method_sends()
    {
        $subject = $this->newSubject();
        $message = $this->generateValidMessage();
        $this->assertSame(1, $subject->send($message), 'Number of messages sent does not equal 1');
        $this->assertSame([['RawMessage' => ['Data' => $message->toString()]]], $this->ses_client->getSentMails());
    }

    public function test_its_start_is_no_op()
    {
        $this->assertTrue($this->newSubject()->start());
    }

    public function test_its_stop_is_no_op()
    {
        $this->assertTrue($this->newSubject()->stop());
    }

    public function test_it_emits_timer_metric()
    {
        $subject = $this->newSubject();
        $subject->send($this->generateValidMessage());
        $this->metrics->assertCapturedOneTimer('ses-email-sent', 'ok');
    }

    public function test_it_emits_timer_metric_with_err_src_on_failure()
    {
        $this->ses_client = SpyingSESClient::sendRawEmailWillThrow();
        $subject          = $this->newSubject();
        try {
            $subject->send($this->generateValidMessage());
        } catch (\Exception $e) {
            $this->assertEquals('Email send failed: [Exception] You asked me to throw', $e->getMessage());
        }
        $this->metrics->assertCapturedOneTimer('ses-email-sent', 'err');
    }

    public function test_it_logs_success()
    {
        $subject = $this->newSubject();
        $subject->send($this->generateValidMessage());
        $this->assertSame(
            [
                [
                    'level'   => LogLevel::INFO,
                    'message' => 'Emailed foo@bar.com ()',
                    'context' => [
                        'aws_msg_id' => NULL,
                    ],
                ],
            ],
            $this->log->records
        );
    }

    public function test_it_logs_failure()
    {
        $this->ses_client = SpyingSESClient::sendRawEmailWillThrow();
        $subject          = $this->newSubject();
        try {
            $subject->send($this->generateValidMessage());
        } catch (\Exception $e) {
            $this->assertInstanceOf(EmailDeliveryFailedException::class, $e);
            $this->assertEquals('Email send failed: [Exception] You asked me to throw', $e->getMessage());
            $this->assertSame('You asked me to throw', $e->getPrevious()->getMessage());
        }
        $this->assertSame(
            LogLevel::ERROR,
            $this->log->records[0]['level']
        );
        $this->assertSame(
            'Failed to email `foo@bar.com` - [Exception] You asked me to throw',
            $this->log->records[0]['message']
        );
    }

    protected function generateValidMessage(): DummySwift_Message
    {
        $message = DummySwift_Message::withDummyMimeString('DUMMY MESSAGE');
        $message->addTo('foo@bar.com');
        $message->addFrom('other@bar.com');

        return $message;
    }

    protected function getDummy(string $class): MockObject
    {
        return $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableProxyingToOriginalMethods()
            ->getMock();
    }

    protected function newSubject(): SESTransport
    {
        return new SESTransport(
            $this->event_dispatcher,
            $this->ses_client,
            new OperationTimer($this->metrics),
            $this->log
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->event_dispatcher = new Swift_Events_SimpleEventDispatcher;
        $this->ses_client       = new SpyingSESClient();
        $this->metrics          = new ArrayMetricsAgent();
        $this->log              = new TestLogger();
    }

}
