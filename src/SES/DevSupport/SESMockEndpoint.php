<?php

namespace Ingenerator\SwiftMailer\SES\DevSupport;

use Ingenerator\PHPUtils\StringEncoding\JSON;

class SESMockEndpoint
{
    public static function endpoint(string $dump_dir): string
    {
        if ( ! is_dir($dump_dir)) {
            mkdir($dump_dir, 0777, TRUE);
        }

        $base_filename = $dump_dir.'/'.uniqid(date('Y-m-d-H-i-s-u'));
        if (isset($_POST['RawMessage_Data'])) {
            $raw_data = base64_decode($_POST['RawMessage_Data']);
        } else {
            $raw_data = NULL;
        }

        file_put_contents(
            $base_filename.'.email.json',
            JSON::encode(
                [
                    'headers'  => getallheaders(),
                    'post'     => $_POST,
                    'raw_data' => $raw_data,
                ]
            )
        );

        if ($raw_data) {
            file_put_contents(
                $base_filename.'.email.raw_mime.txt',
                $raw_data
            );
            file_put_contents(
                $base_filename.'.email.quoted_printable.txt',
                quoted_printable_decode($raw_data)
            );
        }

        header('Content-Type: text / xml');
        $message_id = '010201739c605ff5-bfdb0702-12d8-4544-9bc5-'.bin2hex(random_bytes(6)).'-'.bin2hex(random_bytes(3));

        return <<<XML
            <SendRawEmailResponse xmlns="http://ses.amazonaws.com/doc/2010-12-01/">
              <SendRawEmailResult>
                <MessageId>$message_id</MessageId>
              </SendRawEmailResult>
              <ResponseMetadata>
                <RequestId>cbfe3bc1-069e-46d2-9b0d-0c960fc98313</RequestId>
              </ResponseMetadata>
            </SendRawEmailResponse>
            XML;
    }
}
