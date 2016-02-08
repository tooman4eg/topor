<?php

class ApiLimitsTest extends Topor\ApiTestCase
{
    public function testLimits()
    {
        $this->markTestSkipped('Not refactored yet');
        $account = $this->createAccount();
        $this->login = $account->phone;
        $this->password = $account->password;

        $resp = $this->get('v1/limits', 200);
        $this->assertCode(200, $resp);
        $this->assertInternalType('array', $resp->data);
        $this->assertGreaterThan(0, $resp->data);
        $this->assertObjectHasAttribute('id', $resp->data[0]);
        $this->assertObjectHasAttribute('title', $resp->data[0]);
        $this->assertObjectHasAttribute('description', $resp->data[0]);
        $this->assertObjectHasAttribute('limit_anonymous', $resp->data[0]);
        $this->assertObjectHasAttribute('limit_authorized', $resp->data[0]);
        $this->assertObjectHasAttribute('status_anonymous', $resp->data[0]);
        $this->assertObjectHasAttribute('status_authorized', $resp->data[0]);

        $this->deleteWallet($account->phone, $account->password);
    }

    public function testReplenishmentLimits()
    {
        $this->markTestSkipped('Not refactored yet');
        $resp = $this->get('v1/replenishment/limits', 200);
        $this->assertCode(200, $resp);
        $this->assertInternalType('object', $resp->data);
        $this->assertObjectHasAttribute('limit', $resp->data);
    }
}
