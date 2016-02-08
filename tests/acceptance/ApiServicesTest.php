<?php

use Topor\SandboxClientServer as Client;

class ApiServicesTest extends Topor\ApiTestCase
{
    /**
     * @expectedException Topor\Exception\BadCredentials
     * @expectedExceptionMessage Wrong login or password
     */
    public function testGetServicesGroupsNotAuthorized()
    {
        $client = (new Client('wrong', 'credentials'));
        $resp = $client->servicesGroups();
    }

    /**
     * @large
     */
    public function testGetServicesGroupsEmpty()
    {
        $resp = $this->defaultClient()->servicesGroups();
        $this->assertInternalType('array', $resp->data);
        $this->assertGreaterThan(0, count($resp->data));

        foreach($resp->data as $service) {
            $this->assertInternalType('object', $service);
            $this->assertNotEmpty($service->name);
            $this->assertObjectHasAttribute('icon_url_32x32', $service);
            $this->assertObjectHasAttribute('services', $service);
            $this->assertInternalType('array', $service->services);
        }
    }

    /**
     * @large
     */
    public function testGetServicesGroupsLastModifiedGreater()
    {
        $resp = $this->defaultClient()->servicesGroups(time());
        $this->assertCount(0, $resp->data);
    }

    /**
     * @large
     */
    public function testGetServicesGroupsLastModifiedLess()
    {
        $resp = $this->defaultClient()->servicesGroups(time() - 1000000);
        $this->assertInternalType('array', $resp->data);
        $this->assertGreaterThan(0, count($resp->data));
    }

    /**
     * @large
     */
    public function testGetService()
    {
        $resp = $this->defaultClient()->servicesGroup($id = 3);
        $this->assertInternalType('object', $resp->data);
        $this->assertEquals($id, $resp->data->id);
        $this->assertEquals('Мобильная связь', $resp->data->name);
        $this->assertEquals('cellular', $resp->data->group);
        $this->assertObjectHasAttribute('icon_url_32x32', $resp->data);
        $this->assertNotEmpty($resp->data->icon_url_32x32);
        $this->assertObjectHasAttribute('services', $resp->data);
        $this->assertInternalType('array', $resp->data->services);
    }

    /**
     * @expectedException Topor\Exception\BadCredentials
     * @expectedExceptionMessage Wrong login or password
     */
    public function testGetServiceByIdNotAuthorized()
    {
        (new Client('wrong', 'credentials'))->service(1);
    }

    public function testGetServiceById()
    {
        $client = $this->defaultClient();
        $services_by_groups = $client->servicesGroups();
        $path = 'v1/services';

        $existing_services = 0;
        foreach ($services_by_groups->data as $group)
        {
            if ($group->services)
            {
                foreach ($group->services as $service)
                {
                    $resp = $client->service($service->id);
                    $this->assertInternalType('object', $resp->data);
                    $this->assertGreaterThan(0, count($resp->data));
                    $this->assertObjectHasAttribute('id', $resp->data);
                    $this->assertObjectHasAttribute('name', $resp->data);
                    $this->assertObjectHasAttribute('params', $resp->data);
                    $this->assertInternalType('array', $resp->data->params);

                    $existing_services++;
                }
            }
        }
        $this->assertGreaterThan(0, $existing_services, "Response from $path return groups without services");
    }

    public function testGetServices()
    {
        $client = $this->defaultClient();
        $resp = $client->services();

        $this->assertInternalType('array', $resp->data);
        $this->assertGreaterThan(0, count($resp->data));
    }

    public function testGetMobileServiceById()
    {
        $resp = $this->defaultClient()->suggestMobileService('+79851111111');
        $this->assertObjectHasAttribute('data', $resp, 'No data in response');
        $this->assertInternalType('object', $resp->data);
        $this->assertEquals('МТС', $resp->data->name);
    }
}
