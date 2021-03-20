<?php


namespace Ingenerator\SwiftMailer\SES\Transport;


use Aws\Result;
use Aws\Ses\SesClient;
use Ingenerator\PHPUtils\Monitoring\MetricId;
use Ingenerator\PHPUtils\Monitoring\OperationTimer;
use Ingenerator\SwiftMailer\Common\Transport\AbstractStatelessSwiftTransport;
use Ingenerator\SwiftMailer\Common\Transport\EmailDeliveryFailedException;
use Psr\Log\LoggerInterface;
use Swift_Events_EventDispatcher;
use Swift_Mime_SimpleMessage;
use Throwable;
use function array_keys;
use function get_class;
use function implode;

class SESTransport extends AbstractStatelessSwiftTransport
{
    protected LoggerInterface $logger;

    protected OperationTimer $timer;

    protected SesClient $ses_client;

    public function __construct(
        Swift_Events_EventDispatcher $event_dispatcher,
        SesClient $ses_client,
        OperationTimer $timer,
        LoggerInterface $logger
    ) {
        parent::__construct($event_dispatcher);
        $this->ses_client = $ses_client;
        $this->timer      = $timer;
        $this->logger     = $logger;
    }

    protected function doSend(Swift_Mime_SimpleMessage $message): bool
    {
        return $this->timer->timeOperation(
            function (MetricId $metric) use ($message): bool {
                try {
                    $result = $this->ses_client->sendRawEmail(['RawMessage' => ['Data' => $message->toString()]]);
                    $this->logSuccess($message, $result);

                    return TRUE;
                } catch (Throwable $e) {
                    $metric->setSource('err');
                    $this->logFailure($message, $e);
                    throw new EmailDeliveryFailedException(
                        sprintf('Email send failed: [%s] %s', get_class($e), $e->getMessage()),
                        0,
                        $e
                    );
                }
            },
            'ses-email-sent',
            'ok'
        );
    }

    protected function getRecipientStringForLogging(Swift_Mime_SimpleMessage $message): string
    {
        return implode(',', array_keys($message->getTo()));
    }

    protected function logFailure(Swift_Mime_SimpleMessage $message, Throwable $e): void
    {
        $recipient_string = $this->getRecipientStringForLogging($message);
        $this->logger->error(
            sprintf('Failed to email `%s` - [%s] %s', $recipient_string, get_class($e), $e->getMessage()),
            [
                'exception'      => $e,
                'msg_subject'    => $message->getSubject(),
            ]
        );
    }

    protected function logSuccess(Swift_Mime_SimpleMessage $message, Result $result): void
    {
        $recipient_string = $this->getRecipientStringForLogging($message);
        $this->logger->info(
            sprintf('Emailed %s (%s)', $recipient_string, $message->getSubject()),
            [
                'aws_msg_id'     => $result->get('MessageId'),
            ]
        );
    }

}
