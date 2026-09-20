<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Delivery Confirmation Code</title>
</head>
<body>
    <p>Hello {{ $recipientName }},</p>

    <p>
        Your Taala Crystal delivery {{ $deliveryReference }} is on the way.
    </p>

    <p>
        Your delivery confirmation code is:
        <strong style="font-size: 1.4rem; letter-spacing: 0.2rem;">
            {{ $code }}
        </strong>
    </p>

    <p>
        Give this code to the delivery driver only after you have received and
        checked your delivery.
    </p>

    <p>This code expires at {{ $expiresAt }}.</p>

    <p>
        If you did not expect this delivery, do not share the code.
    </p>
</body>
</html>
