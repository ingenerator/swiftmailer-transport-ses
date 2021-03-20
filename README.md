# Swift Mailer AWS Simple Email Service (SES) Transport

Allows for sending email using [Swift Mailer](https://swiftmailer.symfony.com/) over HTTP uses [AWS Simple Email Service](https://aws.amazon.com/ses/)

```php
// Create the SES client
$ses_client = SESTransportFactory::buildSESClient(
    [
        'credentials' => [
            'key'    => 'AWS_ACCESS_KEY',
            'secret' => 'AWS_SECRET',
        ],
    ]
);

// Create the transport
$transport = SESTransportFactory::buildSESTransport(
    $ses_client,
    new OperationTimer(new NullMetricsAgent()),
    new NullLogger()
);

// Create the mailer
$mailer = new Swift_Mailer($transport);
```
