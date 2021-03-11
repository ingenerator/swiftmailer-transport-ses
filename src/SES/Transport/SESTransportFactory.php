<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */


namespace Ingenerator\SwiftMailer\SES\Transport;


use Aws\Ses\SesClient;
use Ingenerator\PHPUtils\Monitoring\MetricsInterface;
use Ingenerator\PHPUtils\Monitoring\OperationTimer;
use Psr\Log\LoggerInterface;
use Swift_DependencyContainer;
use Swift_Events_EventListener;
use function array_merge;

class SESTransportFactory
{
    public static function buildSESClient(array $client_options): SesClient
    {
        return new SesClient(
            array_merge(
                [
                    'region'  => 'eu-west-1',
                    'version' => '2010-12-01',
                    'http'    => [
                        // https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html#config-http
                        // Number of seconds to wait for initial connection
                        'connect_timeout' => 5,
                        // Number of seconds to wait for actual processing
                        'timeout'         => 5,
                    ],
                    'retries' => [
                        // https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html#config-retries
                        // Doesn't retry things that it thinks are unlikely to succeed (e.g. rate limits)
                        // It doesn't seem easy to configure the actual backoff rate or max backoff, we will try 2
                        // requests in quick succession (so could be 20 seconds worst case) before we give up.
                        'mode'         => 'standard',
                        'max_attempts' => 2,
                    ],
                ],
                $client_options
            )
        );
    }

    public static function buildSESTransport(
        SesClient $ses_client,
        OperationTimer $timer,
        LoggerInterface $logger,
        Swift_Events_EventListener ...$plugins
    ): SESTransport {
        $event_dispatcher = Swift_DependencyContainer::getInstance()->lookup('transport.eventdispatcher');
        $transport        = new SESTransport($event_dispatcher, $ses_client, $timer, $logger);

        foreach ($plugins as $plugin) {
            $transport->registerPlugin($plugin);
        }

        return $transport;
    }
}
