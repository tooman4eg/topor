<?php

use Topor\SandboxClientServer as Client;

class ApiCardsTest extends Topor\ApiTestCase
{
    function testCreateCard()
    {
        $client = $this->client($this->createAccount());
        $resp = $client->createCard();
        $card_id = $resp->data->id;

        $result = $this->postCardAtUrl(
            stripslashes($resp->data->payment_page_url),
            $this->generateCardData()
        );
        $this->assertTrue($result, "Card data doesn't entered correctly");

        $attempts = 10;
        while ($attempts--) {
            $resp = $client->card($card_id);
            if ($resp->data->state == 'active') {
                break;
            }
            sleep(1);
        }
        $this->assertGreaterThan(0, $attempts);
    }

    /**
     * @expectedException Topor\Exception\BadCredentials
     * @expectedExceptionMessage Wrong login or password
     */
    public function testGetCardsNotAuthorized()
    {
        (new Client('wrong', 'credentials'))->cards();
    }

    function testCards()
    {
        $client = $this->client($this->createAccount());
        $this->assertCount(0, $client->cards()->data);

        $resp = $client->createCard();
        $this->assertCount(1, $client->cards()->data);
        $resp->data->id;

        $result = $this->postCardAtUrl(
            stripslashes($resp->data->payment_page_url),
            $this->generateCardData()
        );
        $this->assertTrue($result, "Card data doesn't entered correctly");

        $attempts = 10;
        while ($attempts--) {
            if ($client->cards()->data[0]->state == 'active') {
                break;
            }
            sleep(1);
        }
        $this->assertGreaterThan(0, $attempts);

        $this->assertEquals('active', $client->cards()->data[0]->state);
    }

    function testDeleteCard()
    {
        $client = $this->client($this->createAccount());
        $this->assertCount(0, $client->cards()->data);

        $resp = $client->createCard();
        $card_id = $resp->data->id;
        $this->assertCount(1, $client->cards()->data);

        $client->deleteCard($card_id);
        $this->assertCount(0, $client->cards()->data);

        try
        {
            $client->card($card_id);
            $this->fail('Exception not raised');
        }
        catch(\Topor\Exception\NotFound $e) {}
    }
}
