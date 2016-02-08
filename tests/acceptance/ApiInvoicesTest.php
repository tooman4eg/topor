<?php

class ApiInvoicesTest extends Topor\ApiTestCase
{
    function testCreateInvoice()
    {
        $merchant = $this->client($this->createAccount());
        $man = $this->client($this->createAccount());

        $merchant->createInvoice($man->phone, 350, 'The test invoice');
    }

    function testInboundInvoices()
    {
        $merchant = $this->client($this->createAccount());
        $man = $this->client($this->createAccount());

        $merchant->createInvoice(
            $man->phone,
            $amount = 350,
            $message = 'The test invoice'
        );
        $invoices = $man->inboundInvoices()->data;
        $this->assertCount(1, $invoices);
        $this->assertEquals($man->phone, $invoices[0]->payer);
        $this->assertEquals($merchant->phone, $invoices[0]->recipient);
        $this->assertEquals($amount, $invoices[0]->amount);
        $this->assertEquals($message, $invoices[0]->message);
        $this->assertEquals('created', $invoices[0]->status);
    }

    function testOutboundInvoices()
    {
        $merchant = $this->client($this->createAccount());
        $man = $this->client($this->createAccount());

        $merchant->createInvoice(
            $man->phone,
            $amount = 350,
            $message = 'The test invoice'
        );

        $invoices = $merchant->outboundInvoices()->data;
        $this->assertCount(1, $invoices);
        $this->assertEquals($man->phone, $invoices[0]->payer);
        $this->assertEquals($merchant->phone, $invoices[0]->recipient);
        $this->assertEquals($amount, $invoices[0]->amount);
        $this->assertEquals($message, $invoices[0]->message);
        $this->assertEquals('created', $invoices[0]->status);
    }

    function testDuplicateInvoice()
    {
        $merchant = $this->client($this->createAccount());
        $man = $this->client($this->createAccount());

        $merchant->createInvoice(
            $man->phone,
            $amount = 350,
            $message = 'The test invoice'
        );

        $invoice = $merchant->outboundInvoices()->data[0];
        $merchant->duplicateInvoice($invoice->_id);

        $invoices = $merchant->outboundInvoices()->data;
        $this->assertCount(2, $invoices);
        $this->assertEquals($man->phone, $invoices[1]->payer);
        $this->assertEquals($merchant->phone, $invoices[1]->recipient);
        $this->assertEquals($amount, $invoices[1]->amount);
        $this->assertEquals($message, $invoices[1]->message);
        $this->assertEquals('created', $invoices[1]->status);
    }

    function testCancelInvoice()
    {
        $merchant = $this->client($this->createAccount());
        $man = $this->client($this->createAccount());

        $merchant->createInvoice(
            $man->phone,
            $amount = 350,
            $message = 'The test invoice'
        );

        $invoice = $merchant->outboundInvoices()->data[0];
        $merchant->cancelInvoice($invoice->_id);

        $invoices = $merchant->outboundInvoices()->data;
        $this->assertCount(1, $invoices);
        $this->assertEquals($man->phone, $invoices[0]->payer);
        $this->assertEquals($merchant->phone, $invoices[0]->recipient);
        $this->assertEquals($amount, $invoices[0]->amount);
        $this->assertEquals($message, $invoices[0]->message);
        $this->assertEquals('canceled', $invoices[0]->status);
    }

    function testPayInvoice()
    {
        $merchant = $this->client($this->createAccount());
        $this->fillPersonalInfo($merchant);
        $this->getAdminClient()->markAccountAsVerified($merchant->phone);

        $man = $this->client($this->createAccount());
        $this->fillPersonalInfo($man);
        $this->getAdminClient()->markAccountAsVerified($man->phone);

        $merchant->createInvoice(
            $man->phone,
            $amount = 350,
            $message = 'The test invoice'
        );

        $invoice = $man->inboundInvoices()->data[0];
        $resp = $man->payInvoice($invoice->_id);
        $this->assertEquals(200, $resp->meta->code);
        $this->assertEquals('created', $resp->data->status);
        $this->assertNotEmpty($resp->data->id);
        $this->assertNotEmpty($resp->data->client_payment_id);
    }
}
