<?php


namespace Ingenerator\SwiftMailer\Common\Transport;


use Ingenerator\PHPUtils\StringEncoding\JSON;
use InvalidArgumentException;
use Swift_Events_EventDispatcher;
use Swift_Events_EventListener;
use Swift_Events_SendEvent;
use Swift_Mime_SimpleMessage;
use Swift_Transport;
use function get_class;

/**
 * Implemented because there seems to be a lot of boilerplate in the Swift_Transport implementations
 * and it's hard to see what's actually the responsibility of the transport in pure "send the message"
 * terms.
 *
 */
abstract class AbstractStatelessSwiftTransport implements Swift_Transport
{
    /**
     * Prop named for parity with Swift's own transports so we can see the duplicate code
     *
     * @var Swift_Events_EventDispatcher
     */
    protected Swift_Events_EventDispatcher $_eventDispatcher;

    public function __construct(Swift_Events_EventDispatcher $event_dispatcher)
    {
        $this->_eventDispatcher = $event_dispatcher;
    }

    public function isStarted(): bool
    {
        return TRUE;
    }

    public function start(): bool
    {
        return TRUE;
    }

    public function stop(): bool
    {
        return TRUE;
    }

    public function ping(): bool
    {
        return TRUE;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = NULL): int
    {
        $sent_count       = 0;
        $failedRecipients = (array) $failedRecipients;

        if ($evt = $this->_eventDispatcher->createSendEvent($this, $message)) {
            $this->_eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
            if ($evt->bubbleCancelled()) {
                return 0;
            }
        }

        // Enforce that the message is kept quite simple as it simplifies our handling
        $this->enforceMaxOneAddress($message->getFrom(), 'from');
        $this->enforceMaxOneAddress($message->getTo(), 'to');

        if ($success = $this->doSend($message)) {
            $sent_count += 1;
        }

        if ($evt) {
            $evt->setResult($success ? Swift_Events_SendEvent::RESULT_SUCCESS : Swift_Events_SendEvent::RESULT_FAILED);
            $this->_eventDispatcher->dispatchEvent($evt, 'sendPerformed');
        }

        return $sent_count;
    }

    /**
     * @param array  $addresses
     * @param string $type
     */
    protected function enforceMaxOneAddress(array $addresses, string $type): void
    {
        if (count($addresses) > 1) {
            throw new InvalidArgumentException(
                get_class($this).' does not support multiple `'.$type.'` address:'.JSON::encode($addresses)
            );
        }
    }

    abstract protected function doSend(Swift_Mime_SimpleMessage $message): bool;

    public function registerPlugin(Swift_Events_EventListener $plugin): void
    {
        $this->_eventDispatcher->bindEventListener($plugin);
    }
}
