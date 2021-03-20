<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\SwiftMailer\SES\DevSupport;

use BadMethodCallException;
use Ingenerator\Mailhook\Email;
use Ingenerator\SwiftMailer\SES\DevSupport\SESSimulatorEmailParser;
use PHPUnit\Framework\TestCase;
use Swift_Message;
use UnexpectedValueException;

class SESSimulatorEmailParserTest extends TestCase
{

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(SESSimulatorEmailParser::class, $this->newSubject());
    }

    public function test_its_parse_method_throws()
    {
        $subject = $this->newSubject();
        $this->expectException(BadMethodCallException::class);
        $subject->parse('');
    }

    public function test_its_parseSimulatorCapture_throws_if_captured_action_is_not_SendRawEmail()
    {
        $subject = $this->newSubject();
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected AWS action SendRawEmail, got UnexpectedAction');
        $subject->parseSimulatorCapture(['post' => ['Action' => 'UnexpectedAction']]);
    }

    public function test_it_can_parseSimulatorCapture()
    {
        $subject = $this->newSubject();
        $email   = $subject->parseSimulatorCapture(
            [
                'post'     => ['Action' => 'SendRawEmail'],
                'raw_data' => <<<MSG
                                Date: Thu, 30 Jul 2020 22:55:04 +0100
                                Subject: inGenerator - Account Activation
                                From: NoOne <noreply@somewhere.test>
                                To: GPva067Zj sCADSouhPQ4vatf
                                 <recipient@nowhere.test>
                                MIME-Version: 1.0
                                
                                Test message https://www.ingenerator.com/
                                MSG,
            ]
        );
        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('recipient@nowhere.test', $email->getTo());
        $this->assertSame('inGenerator - Account Activation', $email->getSubject());
        $this->assertSame('Test message https://www.ingenerator.com/', $email->getContent());
        $this->assertSame(['https://www.ingenerator.com/'], $email->getLinks());
    }

    public function test_parses_message()
    {
        $msg = new Swift_Message('About a thing', 'hey, whatever');
        $msg->addTo('some.one@test.com', 'Some One');

        $subject = $this->newSubject();
        $email   = $subject->parseSimulatorCapture(
            ['post' => ['Action' => 'SendRawEmail'], 'raw_data' => $msg->toString()]
        );
        $this->assertSame('some.one@test.com', $email->getTo());
        $this->assertSame('About a thing', $email->getSubject());
        $this->assertSame('hey, whatever', $email->getContent());
    }

    public function test_parses_message_with_html()
    {
        $body = '<html><body><h1 class="heading">Hello</h1><p>Here is a test html message with a <a href="http://example.test">link</a></p></body></html>';
        $msg  = new Swift_Message('Some html', $body, 'text/html');
        $msg->addTo('some.one@test.com', 'Some One');
        $msg->addPart('This,is,a,basic,CSV', 'text/csv');

        $subject = $this->newSubject();
        $email   = $subject->parseSimulatorCapture(
            ['post' => ['Action' => 'SendRawEmail'], 'raw_data' => $msg->toString()]
        );
        $this->assertSame('some.one@test.com', $email->getTo());
        $this->assertSame('Some html', $email->getSubject());
        $this->assertSame(['http://example.test'], $email->getLinks());
    }

    protected function newSubject(): SESSimulatorEmailParser
    {
        return new SESSimulatorEmailParser();
    }

}
