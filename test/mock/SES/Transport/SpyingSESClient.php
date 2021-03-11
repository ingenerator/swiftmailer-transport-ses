<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */


namespace test\mock\Ingenerator\SwiftMailer\SES\Transport;


use Aws\Result;
use Aws\Ses\SesClient;

class SpyingSESClient extends SesClient
{
    protected array $sent_mails = [];
    protected bool $should_throw = FALSE;

    public function __construct()
    {
    }

    public static function sendRawEmailWillThrow()
    {
        $s               = new self;
        $s->should_throw = TRUE;

        return $s;
    }

    public function getSentMails(): array
    {
        return $this->sent_mails;
    }

    public function sendRawEmail(array $args = []): Result
    {
        if ($this->should_throw) {
            throw new \Exception('You asked me to throw');
        }

        $this->sent_mails[] = $args;

        return new Result();
    }

}
