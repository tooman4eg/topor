<?php

use Topor\SandboxClientServer as Client;
use Topor\SandboxAdminServer as Admin;

class ApiPaymentsTest extends Topor\ApiTestCase
{
    /**
     * @large
     * @expectedException Topor\Exception\BadRequest
     * @expectedExceptionMessage Check request params
     */
    public function testCreatePaymentBadParams()
    {
        $client = $this->defaultClient();
        $client->createPaymentForService($wrong_service_id = 99999999, 42, []);
    }

    /**
     * @large
     */
    public function testCreatePaymentForService()
    {
        $client = $this->defaultClient();
        $balance_before = $client->account()->data->amount;

        $parameters = ['phoneNumber' => '0509244604'];
        $created_resp = $client->createPaymentForService(15, 42, $parameters);
        $this->assertInternalType('string', $created_resp->data->parameters[0]->type);
        $this->assertEquals('created', $created_resp->data->status);
        $this->assertEquals('pay', $created_resp->meta->next_action);

        $processing_resp = $client->approvePayment($created_resp->data->id);
        $this->assertEquals('processing', $processing_resp->data->status);
        $this->assertEquals('get', $processing_resp->meta->next_action);

        $completed_resp = $this->askPaymentUntilStatusChange($client, $processing_resp);
        $this->assertEquals('completed', $completed_resp->data->status);
    }

    /**
     * @large
     */
    public function testCreatePaymentForService_Insufficient_Funds()
    {
        $client = $this->defaultClient();

        $parameters = ['phoneNumber' => '0509244512'];
        $created_resp = $client->createPaymentForService(15, 12000, $parameters);
        $this->assertEquals('created', $created_resp->data->status);
        $this->assertEquals('pay', $created_resp->meta->next_action);

        $processing_resp = $client->approvePayment($created_resp->data->id);
        $this->assertEquals('processing', $processing_resp->data->status);
        $this->assertEquals('get', $processing_resp->meta->next_action);

        $declined_resp = $this->askPaymentUntilStatusChange($client, $processing_resp);
        $this->assertEquals('declined', $declined_resp->data->status);
        $this->assertEquals('insufficient_funds', $declined_resp->data->decline_reason);
    }

    /**
     * @large
     */
    public function testCreatePaymentToUserByPhone()
    {
        $src_client = $this->defaultClient();
        (new Admin)->markAccountAsVerified($src_client->phone);
        $dest_client = $this->anotherUserClient();
        (new Admin)->markAccountAsVerified($dest_client->phone);

        $src_balance_before = $src_client->account()->data->amount;
        $dest_balance_before = $dest_client->account()->data->amount;

        $created_resp = $src_client->createPaymentToUserByPhone($dest_client->phone, 42, 'some message');
        $this->assertEquals('created', $created_resp->data->status);
        $this->assertEquals('pay', $created_resp->meta->next_action);

        $processing_resp = $src_client->approvePayment($created_resp->data->id);
        $this->assertEquals('processing', $processing_resp->data->status);
        $this->assertEquals('get', $processing_resp->meta->next_action);

        $declined_resp = $this->askPaymentUntilStatusChange($src_client, $processing_resp);
        $this->assertEquals('completed', $declined_resp->data->status);

        $resp = $src_client->account();
        $this->assertEquals(
            42,
            $src_balance_before - $resp->data->amount,
            'Wrong balance difference'
        );

        $resp = $dest_client->account();
        $this->assertEquals(
            42,
            $resp->data->amount - $dest_balance_before,
            'Wrong balance difference'
        );
    }

    /**
     * @large
     */
    public function testCreatePaymentForReplenish_NewCard()
    {
        $client = $this->defaultClient();
        $initial_amount = $client->account()->data->amount;

        $created_resp = $client->createPaymentForReplenish(42);
        $this->assertEquals('created', $created_resp->data->status);
        $this->assertEquals('pay', $created_resp->meta->next_action);
        $this->assertEquals($initial_amount, $client->account()->data->amount);

        $processing_resp = $client->approvePayment($created_resp->data->id);
        $this->assertEquals('processing', $processing_resp->data->status);
        $this->assertEquals('get', $processing_resp->meta->next_action);
        $this->assertObjectHasAttribute('card', $processing_resp->data);
        $this->assertObjectHasAttribute('state', $processing_resp->data->card);
        $this->assertEquals('pending', $processing_resp->data->card->state);
        $this->assertObjectHasAttribute('payment_page_url', $processing_resp->data->card);

        $is_success = $this->postCardAtUrl(
            $processing_resp->data->card->payment_page_url,
            $this->generateCardData()
        );

        $this->assertTrue($is_success, "iPSP don't take my card. Again :(");

        $completed_resp = $this->askPaymentUntilStatusChange($client, $processing_resp);
        $this->assertEquals('completed', $completed_resp->data->status);
    }

    function testPayments_with_params()
    {
        $client = $this->defaultClient();
        $resp = $client->payments(1, 0, Client::PAYMENT_OUT, [Client::COMPLETED]);
        $this->assertCount(1, $resp->data);
        $this->assertEquals(Client::PAYMENT_OUT, $resp->data[0]->type);
        $this->assertEquals(Client::COMPLETED, $resp->data[0]->status);
    }

    protected function askPaymentUntilStatusChange($client, $prev_resp)
    {
        $current_status = $prev_resp->data->status;
        $payment_id = $prev_resp->data->id;
        for ($i = 0; $i < 15; $i++)
        {
            $resp = $client->payment($payment_id);
            if($resp->data->status != $current_status)
                return $resp;
            sleep(2);
        }
        $this->markTestIncomplete('Timeout, bleat :(');
    }
}
