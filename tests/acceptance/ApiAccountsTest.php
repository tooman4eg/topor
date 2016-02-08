<?php

use Topor\SandboxClientServer as Client;

class ApiAccountsTest extends Topor\ApiTestCase
{
    /**
     * @param $phone
     * @param $password
     * @param $expected_code
     *
     * @dataProvider wrongAccountData
     */
    function testAccountCreate_Negative($phone, $password, $expected_code)
    {
        try {
            (new Client($phone, $password))->createAccount();
        }
        catch(\Topor\Exception\UnprocessableEntity $e)
        {
            $this->assertEquals($expected_code, $e->responseHttpCode());
        }
    }

    function wrongAccountData()
    {
        return [
            ['', 'password', 422],
            ['+1200' . rand(1111111, 9999999), '', 422],
            [1, 'some_password', 422],
            [1, 1, 422],
            [1, 123123123, 422],
            ['+1200' . rand(1111111, 9999999), 0, 422],
        ];
    }

    function testAccount_DefaultValues()
    {
        $credentials = $this->createAccount();
        $resp = $this->client($credentials)->account();
        $this->assertEquals($credentials->phone, $resp->data->phone);
        $this->assertGreaterThan(0, $resp->data->amount);
        $this->assertNull($resp->data->name);
        $this->assertEquals(Client::ANONYMOUS, $resp->data->level);
        $this->assertEquals(false, $resp->data->verified);
        $this->assertEquals(Client::PERSONALITY_EMPTY, $resp->data->person_status);
    }

    function testAccount()
    {
        $resp = $this->defaultClient()->account();
        $this->assertEquals('+79270000001', $resp->data->phone);
        $this->assertGreaterThan(0, $resp->data->amount);
        $this->assertEquals('Пиэчпий Мбанков', $resp->data->name);
        $this->assertEquals(Client::ANONYMOUS, $resp->data->level);
        $this->assertEquals(true, $resp->data->verified);
        $this->assertEquals(
            Client::PERSONALITY_VERIFIED,
            $resp->data->person_status
        );
    }

    /**
     * @large
     * @expectedException Topor\Exception\PhoneAlreadyExists
     * @expectedExceptionMessage Phone already exists
     */
    function testAccount_used_phone()
    {
        $client = $this->client($this->createAccount());
        $client->createAccount();
    }

    public function testSendPasswordResetCode()
    {
        $credentials = $this->client($this->createAccount());
        $resp = (new Client(null, null))->sendPasswordResetCode($credentials->phone);
        $this->assertNotEmpty($resp->dev->security_code);
    }

    public function testAccountPasswordChange()
    {
        $account = $this->createAccount();
        $phone = $account->phone;
        $password = $account->password;
        $new_password = 'NewPassword';

        $resp = (new Client(null, null))->sendPasswordResetCode($phone);
        $this->assertNotEmpty($resp->dev->security_code);
        $reset_code = $resp->dev->security_code;

        try {
            (new Client(null, null))->resetPassword('wrong_code', $phone, $password);
            $this->fail('Exception not raised on invalid code');
        }
        catch(\Topor\Exception\UnprocessableEntity $e) {}

        (new Client(null, null))->resetPassword($reset_code, $phone, $new_password);
        $account->password = $new_password;
        $resp = $this->client($account)->account();
        $this->assertEquals(200, $resp->meta->code);
    }

    public function testWalletSetPersonalInfo()
    {
        $account = $this->createAccount();

        $client = $this->client($account);
        $resp = $client->fillPersonalInfo([
            'family_name' => 'db.mbank.find({$%#$%^#$%})<h1>hacked</h1>',
            'given_name' => 'db.mbank.find({$%#$%^#$%})<h2>hacked</h2>'
        ]);

        $this->assertEquals('db.mbank.find({$%#$%^#$%})hacked', $resp->data->family_name);
        $this->assertEquals('db.mbank.find({$%#$%^#$%})hacked', $resp->data->given_name);
    }

    function testGetPersonalInfo()
    {
        $account = $this->createAccount();
        $client = $this->client($account);

        $this->assertEquals(new stdClass(), $client->getPersonalInfo()->data);

        $client->fillPersonalInfo($data = [
            'family_name' => 'foo',
            'given_name' => 'bar'
        ]);

        $resp = $client->getPersonalInfo();
        $this->assertEquals($data['family_name'], $resp->data->family_name);
        $this->assertEquals($data['given_name'], $resp->data->given_name);
    }
}
