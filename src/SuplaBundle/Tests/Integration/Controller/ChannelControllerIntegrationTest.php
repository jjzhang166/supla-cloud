<?php
/*
 Copyright (C) AC SOFTWARE SP. Z O.O.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace SuplaBundle\Tests\Integration\Controller;

use SuplaBundle\Auth\OAuthScope;
use SuplaBundle\Auth\SuplaOAuth2;
use SuplaBundle\Entity\EntityUtils;
use SuplaBundle\Entity\Main\DirectLink;
use SuplaBundle\Entity\Main\IODeviceChannel;
use SuplaBundle\Entity\Main\Location;
use SuplaBundle\Entity\Main\PushNotification;
use SuplaBundle\Entity\Main\Scene;
use SuplaBundle\Entity\Main\SceneOperation;
use SuplaBundle\Entity\Main\User;
use SuplaBundle\Enums\ActionableSubjectType;
use SuplaBundle\Enums\ChannelConfigChangeScope;
use SuplaBundle\Enums\ChannelFunction;
use SuplaBundle\Enums\ChannelFunctionAction;
use SuplaBundle\Enums\ChannelFunctionBitsFlags;
use SuplaBundle\Enums\ChannelType;
use SuplaBundle\Model\ApiVersions;
use SuplaBundle\Model\UserConfigTranslator\SubjectConfigTranslator;
use SuplaBundle\Supla\SuplaServerMock;
use SuplaBundle\Tests\Integration\IntegrationTestCase;
use SuplaBundle\Tests\Integration\Traits\ResponseAssertions;
use SuplaBundle\Tests\Integration\Traits\SuplaApiHelper;
use SuplaBundle\Tests\Integration\Traits\SuplaAssertions;
use SuplaDeveloperBundle\DataFixtures\ORM\NotificationsFixture;
use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;

/** @small */
class ChannelControllerIntegrationTest extends IntegrationTestCase {
    use SuplaApiHelper;
    use ResponseAssertions;
    use SuplaAssertions;

    /** @var User */
    private $user;
    /** @var \SuplaBundle\Entity\Main\IODevice */
    private $device;
    /** @var \SuplaBundle\Entity\Main\Location */
    private $location;
    /** @var \SuplaBundle\Entity\Main\OAuth\AccessToken */
    private $peronsalToken;

    protected function initializeDatabaseForTests() {
        $this->user = $this->createConfirmedUser();
        $this->location = $this->createLocation($this->user);
        $this->device = $this->createDevice($this->location, [
            [ChannelType::RELAY, ChannelFunction::LIGHTSWITCH],
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEDOORLOCK],
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEGATE],
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEROLLERSHUTTER],
            [ChannelType::DIMMERANDRGBLED, ChannelFunction::DIMMERANDRGBLIGHTING],
            [ChannelType::VALVEOPENCLOSE, ChannelFunction::VALVEOPENCLOSE],
            [ChannelType::THERMOMETER, ChannelFunction::THERMOMETER],
            [ChannelType::RELAY, ChannelFunction::NONE],
            [ChannelType::RELAY, ChannelFunction::LIGHTSWITCH],
        ]);
        $this->createDevice($this->location, [
            [ChannelType::RELAY, ChannelFunction::LIGHTSWITCH],
            [ChannelType::THERMOMETERDS18B20, ChannelFunction::THERMOMETER],
        ]);
        $oauth = self::$container->get(SuplaOAuth2::class);
        $this->peronsalToken = $oauth->createPersonalAccessToken($this->user, 'TEST', new OAuthScope(OAuthScope::getSupportedScopes()));
        $this->getEntityManager()->persist($this->peronsalToken);
        $this->getEntityManager()->flush();
    }

    public function testGettingChannelInfo() {
        $client = $this->createAuthenticatedClient($this->user);
        $channel = $this->device->getChannels()[0];
        $client->request('GET', '/api/channels/' . $channel->getId());
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent());
        $this->assertTrue($content->enabled);
        $this->assertSuplaCommandExecuted('GET-RELAY-VALUE:1,1,1');
    }

    public function testGettingChannelInfoV23() {
        $client = $this->createAuthenticatedClient($this->user);
        $channel = $this->device->getChannels()[0];
        $client->apiRequestV23('GET', '/api/channels/' . $channel->getId());
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertEquals(ChannelFunction::LIGHTSWITCH, $content['functionId']);
        $this->assertEquals(ChannelFunction::LIGHTSWITCH, $content['function']['id']);
        $this->assertNoSuplaCommandsExecuted();
        $this->assertArrayHasKey('param1', $content);
    }

    public function testGettingChannelInfoV24() {
        $client = $this->createAuthenticatedClient($this->user);
        $channel = $this->device->getChannels()[0];
        $client->apiRequestV24('GET', '/api/channels/' . $channel->getId());
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertEquals(ChannelFunction::LIGHTSWITCH, $content['functionId']);
        $this->assertEquals(ChannelFunction::LIGHTSWITCH, $content['function']['id']);
        $this->assertArrayHasKey('relationsCount', $content);
        $this->assertArrayHasKey('ownSubjectType', $content);
        $this->assertArrayNotHasKey('param1', $content);
        $this->assertArrayNotHasKey('configHash', $content);
        $this->assertArrayHasKey('config', $content);
        $this->assertEquals(ActionableSubjectType::CHANNEL, $content['ownSubjectType']);
    }

    public function testGettingChannelInfoV3() {
        $client = $this->createAuthenticatedClient($this->user);
        $channel = $this->device->getChannels()[0];
        $client->apiRequestV3('GET', '/api/channels/' . $channel->getId());
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertEquals(ChannelFunction::LIGHTSWITCH, $content['functionId']);
        $this->assertEquals(ChannelFunction::LIGHTSWITCH, $content['function']['id']);
        $this->assertArrayHasKey('relationsCount', $content);
        $this->assertArrayHasKey('ownSubjectType', $content);
        $this->assertArrayNotHasKey('param1', $content);
        $this->assertArrayHasKey('config', $content);
        $this->assertEquals(ActionableSubjectType::CHANNEL, $content['ownSubjectType']);
    }

    public function testGettingChannelInfoWithDeviceLocationV24() {
        $client = $this->createAuthenticatedClient($this->user);
        $channel = $this->device->getChannels()[0];
        $client->apiRequestV24('GET', '/api/channels/' . $channel->getId() . '?include=iodevice,iodevice.location');
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertEquals(ChannelFunction::LIGHTSWITCH, $content['functionId']);
        $this->assertEquals(ChannelFunction::LIGHTSWITCH, $content['function']['id']);
        $this->assertArrayHasKey('relationsCount', $content);
        $this->assertArrayHasKey('iodevice', $content);
        $this->assertArrayHasKey('location', $content['iodevice']);
        $this->assertArrayNotHasKey('location', $content);
    }

    public function testGettingChannelsWithLocationsV24() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?include=location,iodevice');
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('location', $content[0]);
        $this->assertArrayHasKey('iodevice', $content[0]);
        $this->assertArrayHasKey('relationsCount', $content[0]);
        $this->assertArrayNotHasKey('location', $content[0]['iodevice']);
    }

    public function testFilteringByFunction() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?function=LIGHTSWITCH,DIMMERANDRGBLIGHTING');
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertCount(4, $content);
    }

    public function testFilteringByType() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?type=THERMOMETER');
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertCount(1, $content);
    }

    public function testFilteringBySkipIds() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?skipIds=1,2,3,4');
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertCount(7, $content);
    }

    public function testFilteringByInput() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?io=input');
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertCount(2, $content);
    }

    public function testFilteringByInvalidInput() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?io=unicorn');
        $response = $client->getResponse();
        $this->assertStatusCode(400, $response);
    }

    public function testFilteringByHasFunction() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?hasFunction=1');
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertLessThan(count($this->device->getChannels()) + 2, count($content));
    }

    public function testFilteringByHasFunctionNone() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?hasFunction=0');
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertCount(1, $content);
    }

    public function testFilteringByHasFunctionFalse() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?hasFunction=false');
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertCount(1, $content);
    }

    public function testFilteringByHasFunctionAnything() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?hasFunction=unicorn');
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertLessThan(count($this->device->getChannels()), count($content));
    }

    public function testFilteringByDeviceIds() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?deviceIds=' . $this->device->getId());
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertCount(9, $content);
    }

    public function testGettingChannelsWithDeviceLocationsV24() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels?include=location,iodevice,iodevice.location');
        $response = $client->getResponse();
        $this->assertStatusCode(400, $response);
    }

    /**
     * @dataProvider changingChannelStateDataProvider
     */
    public function testChangingChannelState(int $channelId, string $action, string $expectedCommand, array $additionalRequest = []) {
        $client = $this->createAuthenticatedClient($this->user);
        $request = array_merge(['action' => $action], $additionalRequest);
        $client->request('PATCH', '/api/channels/' . $channelId, [], [], [], json_encode($request));
        $response = $client->getResponse();
        $this->assertStatusCode('2xx', $response);
        $this->assertSuplaCommandExecuted($expectedCommand);
    }

    public function changingChannelStateDataProvider() {
        return [
            [1, 'turn-on', 'ACTION-TURN-ON:1,1,1'],
            [1, 'turn-off', 'ACTION-TURN-OFF:1,1,1'],
            [2, 'open', 'SET-CHAR-VALUE:1,1,2,1'],
            [3, 'open-close', 'SET-CHAR-VALUE:1,1,3,1'],
            [3, 'open', 'ACTION-OPEN:1,1,3'],
            [3, 'close', 'ACTION-CLOSE:1,1,3'],
            [4, 'shut', 'SET-CHAR-VALUE:1,1,4,110'],
            [4, 'reveal', 'SET-CHAR-VALUE:1,1,4,10'],
            [4, 'stop', 'SET-CHAR-VALUE:1,1,4,0'],
            [4, 'shut', 'SET-CHAR-VALUE:1,1,4,50', ['percent' => 40]],
            [4, 'reveal', 'SET-CHAR-VALUE:1,1,4,50', ['percent' => 60]],
            [5, 'set-rgbw-parameters', 'SET-RGBW-VALUE:1,1,5,16711935,58,42,0',
                ['color' => 0xFF00FF, 'color_brightness' => 58, 'brightness' => 42]],
            [5, 'set-rgbw-parameters', 'SET-RGBW-VALUE:1,1,5,16711935,100,0,0', ['color' => '0xFF00FF', 'brightness' => 0]],
            [5, 'set-rgbw-parameters', 'SET-RGBW-VALUE:1,1,5,16711935,100,0,0', ['color' => 0xFF00FF, 'brightness' => 0]],
            [5, 'set-rgbw-parameters', 'SET-RGBW-VALUE:1,1,5,11141290,67,0,0', ['color' => '0xAA00AA', 'brightness' => 0]],
            [5, 'set-rgbw-parameters', 'SET-RGBW-VALUE:1,1,5,16711935,58,42,0',
                ['color' => '0xFF00FF', 'color_brightness' => 58, 'brightness' => 42]],
            [5, 'set-rgbw-parameters', 'SET-RGBW-VALUE:1,1,5,16711935,58,42,1',
                ['color' => '0xFF00FF', 'color_brightness' => 58, 'brightness' => 42, 'turnOnOff' => 1]],
            [5, 'set-rgbw-parameters', 'SET-RGBW-VALUE:1,1,5,16711935,58,42,3',
                ['color' => '0xFF00FF', 'color_brightness' => 58, 'brightness' => 42, 'turnOnOff' => 3]],
            [5, 'set-rgbw-parameters', 'SET-RAND-RGBW-VALUE:1,1,5,58,42',
                ['color' => 'random', 'color_brightness' => 58, 'brightness' => 42]],
            [6, 'open', 'SET-CHAR-VALUE:1,1,6,1'],
            [6, 'close', 'SET-CHAR-VALUE:1,1,6,0'],
            [1, 'copy', 'ACTION-COPY:1,1,1,1,9', ['sourceChannelId' => 9]],
        ];
    }

    public function testTryingToExecuteActionInvalidForChannel() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->request('PATCH', '/api/channels/' . 1, [], [], [], json_encode(array_merge(['action' => 'open'])));
        $response = $client->getResponse();
        $this->assertStatusCode('4xx', $response);
    }

    public function testTryingToExecuteInvalidAction() {
        $client = $this->createAuthenticatedClient($this->user);
        $client->request('PATCH', '/api/channels/' . 1, [], [], [], json_encode(array_merge(['action' => 'unicorn'])));
        $response = $client->getResponse();
        $this->assertStatusCode('4xx', $response);
    }

    public function testChangingChannelRgbwState21() {
        $client = $this->createAuthenticatedClient($this->user);
        $request = ['color' => 0xFF00FF, 'color_brightness' => 58, 'brightness' => 42];
        $client->request('PUT', '/api/channels/5', [], [], $this->versionHeader(ApiVersions::V2_1()), json_encode($request));
        $response = $client->getResponse();
        $this->assertStatusCode('2xx', $response);
        $this->assertSuplaCommandExecuted('SET-RGBW-VALUE:1,1,5,16711935,58,42,0');
    }

    public function testChangingChannelFunctionClearsRelatedSensorInOtherDevices() {
        $client = $this->createAuthenticatedClient();
        $channelParamConfigTranslator = self::$container->get(SubjectConfigTranslator::class);
        $this->simulateAuthentication($this->user);
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::SENSORNO, ChannelFunction::OPENINGSENSOR_GATE],
        ]);
        $sensorChannel = $anotherDevice->getChannels()[0];
        $gateChannel = $this->device->getChannels()->filter(function (IODeviceChannel $channel) {
            return $channel->getFunction()->getId() == ChannelFunction::CONTROLLINGTHEGATE;
        })->first();
        $gateChannel = $this->getEntityManager()->find(IODeviceChannel::class, $gateChannel->getId());
        // assign sensor to the gate from other device
        $channelParamConfigTranslator->setConfig($gateChannel, ['openingSensorChannelId' => $sensorChannel->getId()]);
        $this->getEntityManager()->flush();
        $this->getEntityManager()->refresh($gateChannel);
        $this->assertEquals($sensorChannel->getId(), $gateChannel->getParam2());
        SuplaServerMock::reset();
        $client->apiRequestV24('PUT', '/api/channels/' . $sensorChannel->getId(), [
            'functionId' => ChannelFunction::OPENINGSENSOR_GARAGEDOOR,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $this->getEntityManager()->refresh($gateChannel);
        $this->getEntityManager()->refresh($sensorChannel);
        $this->assertEquals(0, $gateChannel->getParam2(), 'The paired sensor has not been cleared.');
        $this->assertEquals(ChannelFunction::OPENINGSENSOR_GARAGEDOOR, $sensorChannel->getFunction()->getId());
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,1000,70,%d',
            $sensorChannel->getIoDevice()->getId(),
            $sensorChannel->getId(),
            ChannelConfigChangeScope::CHANNEL_FUNCTION
        ));
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,2900,20,%d',
            $gateChannel->getIoDevice()->getId(),
            $gateChannel->getId(),
            ChannelConfigChangeScope::RELATIONS | ChannelConfigChangeScope::JSON_BASIC
        ));
    }

    public function testCanChangeChannelFunctionToNone() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::SENSORNO, ChannelFunction::OPENINGSENSOR_GATE],
        ]);
        $sensorChannel = $anotherDevice->getChannels()[0];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $sensorChannel->getId(), [
            'functionId' => ChannelFunction::NONE,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $sensorChannel = $this->getEntityManager()->find(IODeviceChannel::class, $sensorChannel->getId());
        $this->assertEquals(ChannelFunction::NONE, $sensorChannel->getFunction()->getId());
    }

    public function testChangingChannelFunctionFromPowerswitchToOpeningGateWithParam() {
        $client = $this->createAuthenticatedClient();
        $this->simulateAuthentication($this->user);
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::POWERSWITCH],
        ]);
        $relayChannel = $anotherDevice->getChannels()[0];
        // change function to the opening gate with default params from GUI
        $client->apiRequestV23('PUT', '/api/channels/' . $relayChannel->getId(), [
            'functionId' => ChannelFunction::CONTROLLINGTHEGATEWAYLOCK,
            'param1' => 1000,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $this->getEntityManager()->refresh($relayChannel);
        $this->assertEquals(1000, $relayChannel->getParam1(), 'Opening time has been set.');
        $this->assertEquals(ChannelFunction::CONTROLLINGTHEGATEWAYLOCK, $relayChannel->getFunction()->getId());
    }

    public function testChangingChannelFunctionFromPowerswitchToOpeningGateWithConfig() {
        $client = $this->createAuthenticatedClient();
        $this->simulateAuthentication($this->user);
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::POWERSWITCH],
        ]);
        $relayChannel = $anotherDevice->getChannels()[0];
        // change function to the opening gate with default params from GUI
        $client->apiRequestV3('PUT', '/api/channels/' . $relayChannel->getId(), [
            'functionId' => ChannelFunction::CONTROLLINGTHEGATEWAYLOCK,
            'config' => ['relayTimeMs' => 1500],
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $this->getEntityManager()->refresh($relayChannel);
        $this->assertEquals(1500, $relayChannel->getParam1(), 'Opening time has been set.');
        $this->assertEquals(ChannelFunction::CONTROLLINGTHEGATEWAYLOCK, $relayChannel->getFunction()->getId());
    }

    public function testChangingChannelFunctionFromPowerswitchToOpeningGateWithoutParam() {
        $client = $this->createAuthenticatedClient();
        $this->simulateAuthentication($this->user);
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::POWERSWITCH],
        ]);
        $relayChannel = $anotherDevice->getChannels()[0];
        // change function to the opening gate with default params from GUI
        $client->apiRequestV3('PUT', '/api/channels/' . $relayChannel->getId(), [
            'functionId' => ChannelFunction::CONTROLLINGTHEGATEWAYLOCK,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $this->getEntityManager()->refresh($relayChannel);
        $this->assertEquals(500, $relayChannel->getParam1(), 'Opening time has been set.');
        $this->assertEquals(ChannelFunction::CONTROLLINGTHEGATEWAYLOCK, $relayChannel->getFunction()->getId());
    }

    public function testChangingChannelCaptionToEmoji() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::SENSORNO, ChannelFunction::OPENINGSENSOR_GATE],
        ]);
        $sensorChannel = $anotherDevice->getChannels()[0];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $sensorChannel->getId(), [
            'caption' => 'Gate 🏎️',
            'hidden' => false,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $sensorChannel = $this->getEntityManager()->find(IODeviceChannel::class, $sensorChannel->getId());
        $this->assertEquals('Gate 🏎️', $sensorChannel->getCaption());
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,1000,60,%d',
            $anotherDevice->getId(),
            $sensorChannel->getId(),
            ChannelConfigChangeScope::CAPTION
        ));
        return $sensorChannel->getId();
    }

    /** @depends testChangingChannelCaptionToEmoji */
    public function testChangingChannelVisibilityAndNoCaption($channelId) {
        $sensorChannel = $this->freshEntityById(IODeviceChannel::class, $channelId);
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $sensorChannel->getId(), [
            'caption' => 'Gate 🏎️',
            'hidden' => true,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $sensorChannel = $this->getEntityManager()->find(IODeviceChannel::class, $sensorChannel->getId());
        $this->assertTrue($sensorChannel->getHidden());
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,1000,60,%d',
            $sensorChannel->getIoDevice()->getId(),
            $sensorChannel->getId(),
            ChannelConfigChangeScope::VISIBILITY
        ));
    }

    public function testChangingChannelLocation() {
        $anotherLocation = $this->createLocation($this->user);
        $anotherDevice = $this->createDevice($this->freshEntityById(Location::class, $this->location->getId()), [
            [ChannelType::SENSORNO, ChannelFunction::OPENINGSENSOR_GATE],
        ]);
        $sensorChannel = $anotherDevice->getChannels()[0];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $sensorChannel->getId(), [
            'locationId' => $anotherLocation->getId(),
            'caption' => 'Inny caption',
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $sensorChannel = $this->getEntityManager()->find(IODeviceChannel::class, $sensorChannel->getId());
        $this->assertEquals('Inny caption', $sensorChannel->getCaption());
        $this->assertEquals($anotherLocation->getId(), $sensorChannel->getLocation()->getId());
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,1000,60,%d',
            $anotherDevice->getId(),
            $sensorChannel->getId(),
            ChannelConfigChangeScope::CAPTION | ChannelConfigChangeScope::LOCATION
        ));
        return $sensorChannel->getId();
    }

    /** @depends testChangingChannelLocation */
    public function testChangingChannelLocationToInherited(int $channelId) {
        $sensorChannel = $this->freshEntityById(IODeviceChannel::class, $channelId);
        $client = $this->createAuthenticatedClient();
        SuplaServerMock::reset();
        $client->apiRequestV3('PUT', '/api/channels/' . $channelId, [
            'inheritedLocation' => true,
            'caption' => 'Inny caption',
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $sensorChannel = $this->getEntityManager()->find(IODeviceChannel::class, $sensorChannel->getId());
        $this->assertEquals('Inny caption', $sensorChannel->getCaption());
        $this->assertTrue($sensorChannel->hasInheritedLocation());
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,1000,60,%d',
            $sensorChannel->getIoDevice()->getId(),
            $sensorChannel->getId(),
            ChannelConfigChangeScope::LOCATION
        ));
        return $sensorChannel->getId();
    }

    public function testCannotChangeChannelFunctionToNotSupported() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::SENSORNO, ChannelFunction::OPENINGSENSOR_GATE],
        ]);
        $sensorChannel = $anotherDevice->getChannels()[0];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $sensorChannel->getId(), [
            'functionId' => ChannelFunction::THERMOMETER,
        ]);
        $this->assertStatusCode(400, $client->getResponse());
    }

    public function testChangingChannelFunctionDeletesExistingDirectLinks() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::SENSORNO, ChannelFunction::OPENINGSENSOR_GATE],
        ]);
        $sensorChannel = $anotherDevice->getChannels()[0];
        $directLink = new DirectLink($sensorChannel);
        $directLink->generateSlug(new BCryptPasswordEncoder(4));
        $this->getEntityManager()->persist($directLink);
        $this->getEntityManager()->flush();
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV23('PUT', '/api/channels/' . $sensorChannel->getId(), [
            'functionId' => ChannelFunction::OPENINGSENSOR_GARAGEDOOR,
        ]);
        $this->assertStatusCode(409, $client->getResponse());
        $this->assertEmpty(SuplaServerMock::$executedCommands);
        return $sensorChannel->getId();
    }

    /** @depends testChangingChannelFunctionDeletesExistingDirectLinks */
    public function testChangingChannelFunctionDeletesExistingDirectLinksWhenConfirmed(int $sensorChannelId) {
        $sensorChannel = $this->getEntityManager()->find(IODeviceChannel::class, $sensorChannelId);
        $directLink = $sensorChannel->getDirectLinks()[0];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV23('PUT', '/api/channels/' . $sensorChannel->getId() . '?confirm=1', [
            'functionId' => ChannelFunction::OPENINGSENSOR_GARAGEDOOR,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $this->assertNull($this->getEntityManager()->find(DirectLink::class, $directLink->getId()));
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,1000,70,%d',
            $sensorChannel->getIoDevice()->getId(),
            $sensorChannel->getId(),
            ChannelConfigChangeScope::CHANNEL_FUNCTION | ChannelConfigChangeScope::JSON_BASIC,
        ));
        return $sensorChannel->getId();
    }

    /** @depends testChangingChannelFunctionDeletesExistingDirectLinksWhenConfirmed */
    public function testNotifiesSuplaServerAboutFunctionChange(int $sensorChannelId) {
        $this->assertContains('USER-BEFORE-CHANNEL-FUNCTION-CHANGE:1,' . $sensorChannelId, SuplaServerMock::$executedCommands);
    }

    public function testChangingChannelFunctionDeletesExistingScenes() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEGATEWAYLOCK],
        ]);
        $gateChannel = $anotherDevice->getChannels()[0];
        $scene = new Scene($anotherDevice->getLocation());
        $scene->setOpeartions([new SceneOperation($gateChannel, ChannelFunctionAction::OPEN_CLOSE())]);
        $this->getEntityManager()->persist($scene);
        $this->getEntityManager()->flush();
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $gateChannel->getId() . '?safe=1', [
            'functionId' => ChannelFunction::CONTROLLINGTHEGATE,
        ]);
        $this->assertStatusCode(409, $client->getResponse());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $content['dependencies']['sceneOperations']);
        $this->assertArrayHasKey('owningScene', $content['dependencies']['sceneOperations'][0]); // important for frontend - it displays scene name
        return $gateChannel->getId();
    }

    /** @depends testChangingChannelFunctionDeletesExistingScenes */
    public function testChangingChannelFunctionDeletesExistingDirectLinksWhenNotSafe(int $gateChannelId) {
        $gateChannel = $this->getEntityManager()->find(IODeviceChannel::class, $gateChannelId);
        $sceneOperation = $gateChannel->getSceneOperations()[0];
        $scene = $sceneOperation->getOwningScene();
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $gateChannel->getId(), [
            'functionId' => ChannelFunction::CONTROLLINGTHEGATE,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $this->assertNull($this->getEntityManager()->find(SceneOperation::class, $sceneOperation->getId()));
        $this->assertNull($this->getEntityManager()->find(Scene::class, $scene->getId()));
        return $gateChannel->getId();
    }

    /** @depends testChangingChannelFunctionDeletesExistingDirectLinksWhenNotSafe */
    public function testChangingChannelFunctionAndSettingConfigAtTheSameTimeWorksV23(int $gateChannelId) {
        $gateChannel = $this->getEntityManager()->find(IODeviceChannel::class, $gateChannelId);
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV23('PUT', '/api/channels/' . $gateChannel->getId(), [
            'functionId' => ChannelFunction::CONTROLLINGTHEDOORLOCK,
            'param1' => 1566,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $gateChannel = $this->getEntityManager()->find(IODeviceChannel::class, $gateChannel->getId());
        $this->assertEquals(1566, $gateChannel->getParam1());
    }

    /** @depends testChangingChannelFunctionDeletesExistingDirectLinksWhenNotSafe */
    public function testChangingChannelFunctionAndSettingConfigAtTheSameTimeWorks(int $gateChannelId) {
        $gateChannel = $this->getEntityManager()->find(IODeviceChannel::class, $gateChannelId);
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $gateChannel->getId(), [
            'functionId' => ChannelFunction::CONTROLLINGTHEGATE,
            'config' => ['relayTimeMs' => 1567],
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $gateChannel = $this->getEntityManager()->find(IODeviceChannel::class, $gateChannel->getId());
        $this->assertEquals(1567, $gateChannel->getParam1());
        $this->assertSuplaCommandExecuted('USER-RECONNECT:1');
        return $gateChannel->getId();
    }

    /** @depends testChangingChannelFunctionAndSettingConfigAtTheSameTimeWorks */
    public function testSavingParamsConfigInDatabaseAsJson(int $gateChannelId) {
        $gateChannel = $this->getEntityManager()->find(IODeviceChannel::class, $gateChannelId);
        $expectedConfig = [
            'relayTimeMs' => 1567,
            'openingSensorChannelId' => null,
            'openingSensorSecondaryChannelId' => null,
            'timeSettingAvailable' => true,
            'numberOfAttemptsToOpen' => 5,
            'numberOfAttemptsToClose' => 5,
            'stateVerificationMethodActive' => false,
        ];
        $channelParamConfigTranslator = self::$container->get(SubjectConfigTranslator::class);
        $config = $channelParamConfigTranslator->getConfig($gateChannel);
        $userConfig = array_diff_key($config, ['googleHome' => '', 'alexa' => '', 'closingRule' => '']);
        $this->assertEquals($expectedConfig, $userConfig);
    }

    /** @depends testChangingChannelFunctionDeletesExistingDirectLinksWhenNotSafe */
    public function testCannotStoreRubbishInConfig(int $gateChannelId) {
        $gateChannel = $this->getEntityManager()->find(IODeviceChannel::class, $gateChannelId);
        $client = $this->createAuthenticatedClient();
        SuplaServerMock::reset();
        $client->apiRequestV24('PUT', '/api/channels/' . $gateChannel->getId(), [
            'config' => ['unicorn' => 123],
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $gateChannel = $this->getEntityManager()->find(IODeviceChannel::class, $gateChannel->getId());
        $this->assertArrayNotHasKey('unicorn', $gateChannel->getUserConfig());
        $this->assertSuplaCommandNotExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,2900,20,%d',
            $gateChannel->getIoDevice()->getId(),
            $gateChannel->getId(),
            ChannelConfigChangeScope::JSON_BASIC,
        ));
    }

    /** @depends testChangingChannelFunctionDeletesExistingDirectLinks */
    public function testChangingChannelFunctionToNoneClearsConfig(int $channelId) {
        $channel = $this->getEntityManager()->find(IODeviceChannel::class, $channelId);
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $channel->getId(), ['functionId' => ChannelFunction::NONE]);
        $this->assertStatusCode(200, $client->getResponse());
        $channel = $this->getEntityManager()->find(IODeviceChannel::class, $channel->getId());
        $this->assertEmpty($channel->getUserConfig());
        $this->assertEquals(0, $channel->getParam1());
        $this->assertEquals(0, $channel->getParam2());
        $this->assertEquals(0, $channel->getParam3());
    }

    public function testSettingConfigForActionTrigger() {
        $anotherDevice = $this->createDeviceSonoff($this->getEntityManager()->find(Location::class, $this->location->getId()));
        $trigger = $anotherDevice->getChannels()[2];
        $channel = $this->device->getChannels()[0];
        $actions = ['TURN_ON' => [
            'subjectId' => $channel->getId(), 'subjectType' => ActionableSubjectType::CHANNEL,
            'action' => ['id' => $channel->getPossibleActions()[0]->getId()]]];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $trigger->getId(), ['config' => ['actions' => $actions]]);
        $this->assertStatusCode(200, $client->getResponse());
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $trigger->getId());
        $this->assertArrayHasKey('actions', $trigger->getUserConfig());
        $this->assertCount(1, $trigger->getUserConfig()['actions']);
        return $trigger->getId();
    }

    /** @depends testSettingConfigForActionTrigger */
    public function testGettingChannelActionTriggersCount(int $triggerId) {
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $triggerId);
        $channelWithRelatedTrigger = $this->getEntityManager()->find(IODeviceChannel::class, $trigger->getParam1());
        $this->assertNotNull($channelWithRelatedTrigger);
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels/' . $channelWithRelatedTrigger->getId());
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('relationsCount', $content);
        $this->assertArrayHasKey('actionTriggers', $content['relationsCount']);
        $this->assertEquals(1, $content['relationsCount']['actionTriggers']);
    }

    /** @depends testSettingConfigForActionTrigger */
    public function testUpdatingCaptionForPairedActionTrigger(int $triggerId) {
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $triggerId);
        $channelWithRelatedTrigger = $this->getEntityManager()->find(IODeviceChannel::class, $trigger->getParam1());
        $this->assertNotNull($channelWithRelatedTrigger);
        $client = $this->createAuthenticatedClient($this->user);
        SuplaServerMock::reset();
        $client->apiRequestV24('PUT', '/api/channels/' . $channelWithRelatedTrigger->getId(), [
            'caption' => 'Unicorn channel',
        ]);
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        /** @var IODeviceChannel $trigger */
        $trigger = $this->freshEntity($trigger);
        $this->assertEquals('Unicorn channel AT#1', $trigger->getCaption());
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,2900,140,%d',
            $channelWithRelatedTrigger->getIoDevice()->getId(),
            $channelWithRelatedTrigger->getId(),
            ChannelConfigChangeScope::CAPTION,
        ));
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,11000,700,%d',
            $trigger->getIoDevice()->getId(),
            $trigger->getId(),
            ChannelConfigChangeScope::CAPTION,
        ));
    }

    /** @depends testSettingConfigForActionTrigger */
    public function testGettingIoDeviceChannels(int $triggerId) {
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $triggerId);
        $device = $trigger->getIoDevice();
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', "/api/iodevices/{$device->getId()}/channels");
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertCount(3, $content);
        $this->assertArrayHasKey('relationsCount', $content[0]);
        $this->assertEquals(1, $content[0]['relationsCount']['actionTriggers']);
    }

    /** @depends testSettingConfigForActionTrigger */
    public function testChangingChannelFunctionTriesToClearRelatedActionTriggers(int $triggerId) {
        $channel = $this->device->getChannels()[0];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $channel->getId() . '?safe=1', [
            'functionId' => ChannelFunction::POWERSWITCH,
        ]);
        $this->assertStatusCode(409, $client->getResponse());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('actionTriggers', $content['dependencies']);
        $this->assertCount(1, $content['dependencies']['actionTriggers']);
        $this->assertEquals($triggerId, $content['dependencies']['actionTriggers'][0]['id']);
        return $triggerId;
    }

    /** @depends testChangingChannelFunctionTriesToClearRelatedActionTriggers */
    public function testChangingChannelFunctionClearsRelatedActionTriggers(int $triggerId) {
        $channel = $this->device->getChannels()[0];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $channel->getId(), [
            'functionId' => ChannelFunction::POWERSWITCH,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $triggerId);
        $this->assertEmpty($trigger->getUserConfig()['actions']);
    }

    public function testSettingConfigForActionTriggerV3() {
        $anotherDevice = $this->createDeviceSonoff($this->getEntityManager()->find(Location::class, $this->location->getId()));
        $trigger = $anotherDevice->getChannels()[2];
        $channel = $this->device->getChannels()[0];
        $actions = ['TURN_ON' => [
            'subjectId' => $channel->getId(), 'subjectType' => ActionableSubjectType::CHANNEL,
            'action' => ['id' => $channel->getPossibleActions()[0]->getId()]]];
        $client = $this->createAuthenticatedClient();
        $channelParamConfigTranslator = self::$container->get(SubjectConfigTranslator::class);
        $client->apiRequestV3('PUT', '/api/channels/' . $trigger->getId(), [
            'config' => ['actions' => $actions],
            'configBefore' => $channelParamConfigTranslator->getConfig($trigger),
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $trigger->getId());
        $this->assertArrayHasKey('actions', $trigger->getUserConfig());
        $this->assertCount(1, $trigger->getUserConfig()['actions']);
    }

    public function testChangingChannelFunctionClearsRelatedActionTriggersOnly() {
        $anotherDevice = $this->createDeviceSonoff($this->getEntityManager()->find(Location::class, $this->location->getId()));
        $trigger = $anotherDevice->getChannels()[2];
        $channel1 = $this->device->getChannels()[0];
        $channel2 = $this->device->getChannels()[1];
        $actions = [
            'TURN_ON' => [
                'subjectId' => $channel1->getId(), 'subjectType' => ActionableSubjectType::CHANNEL,
                'action' => ['id' => $channel1->getPossibleActions()[0]->getId()]],
            'TURN_OFF' => [
                'subjectId' => $channel2->getId(), 'subjectType' => ActionableSubjectType::CHANNEL,
                'action' => ['id' => $channel2->getPossibleActions()[0]->getId()]],
        ];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $trigger->getId(), ['config' => ['actions' => $actions]]);
        $this->assertStatusCode(200, $client->getResponse());
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $trigger->getId());
        $this->assertCount(2, $trigger->getUserConfig()['actions']);
        $client->apiRequestV24('PUT', '/api/channels/' . $channel2->getId(), [
            'functionId' => ChannelFunction::CONTROLLINGTHEGATEWAYLOCK,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $trigger->getId());
        $this->assertCount(1, $trigger->getUserConfig()['actions']);
        $this->assertArrayHasKey('TURN_ON', $trigger->getUserConfig()['actions']);
    }

    public function testSettingConfigWithAtActionForActionTrigger() {
        $anotherDevice = $this->createDeviceSonoff($this->getEntityManager()->find(Location::class, $this->location->getId()));
        $trigger = $anotherDevice->getChannels()[2];
        $actions = ['TURN_ON' => [
            'subjectType' => ActionableSubjectType::OTHER,
            'action' => ['id' => ChannelFunctionAction::AT_FORWARD_OUTSIDE]]];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $trigger->getId(), ['config' => ['actions' => $actions]]);
        $this->assertStatusCode(200, $client->getResponse());
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $trigger->getId());
        $this->assertArrayHasKey('actions', $trigger->getUserConfig());
        $this->assertCount(1, $trigger->getUserConfig()['actions']);
    }

    public function testChangingChannelFunctionCanSetSettingForTheNewFunction() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEDOORLOCK],
        ]);
        $channel = $anotherDevice->getChannels()[0];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV23('PUT', '/api/channels/' . $channel->getId(), [
            'functionId' => ChannelFunction::CONTROLLINGTHEGARAGEDOOR,
            'param1' => 2000,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $channel = $this->getEntityManager()->find(IODeviceChannel::class, $channel->getId());
        $this->assertEquals(ChannelFunction::CONTROLLINGTHEGARAGEDOOR, $channel->getFunction()->getId());
        $this->assertEquals(2000, $channel->getParam1());
    }

    public function testChangingChannelFunctionCanSetAltIconImmediately() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEDOORLOCK],
        ]);
        $channel = $anotherDevice->getChannels()[0];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV23('PUT', '/api/channels/' . $channel->getId(), [
            'functionId' => ChannelFunction::POWERSWITCH,
            'altIcon' => 1,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $channel = $this->getEntityManager()->find(IODeviceChannel::class, $channel->getId());
        $this->assertEquals(ChannelFunction::POWERSWITCH, $channel->getFunction()->getId());
        $this->assertEquals(1, $channel->getAltIcon());
    }

    public function testChangingChannelFunctionCanSetConfigImmediately() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEDOORLOCK],
        ]);
        $channel = $anotherDevice->getChannels()[0];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $channel->getId(), [
            'functionId' => ChannelFunction::CONTROLLINGTHEGATE,
            'config' => ['relayTimeMs' => 999],
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $channel = $this->freshEntity($channel);
        $this->assertEquals(999, $channel->getParam1());
    }

    public function testOpeningValveIfFloodingFromWebClient() {
        SuplaServerMock::mockResponse('GET-VALVE-VALUE', "VALUE:1,1\n");
        $client = $this->createAuthenticatedClient($this->user);
        $client->request('PATCH', '/api/channels/6', [], [], [], json_encode(array_merge(['action' => 'open'])));
        $response = $client->getResponse();
        $this->assertStatusCode('2XX', $response);
    }

    public function testOpeningValveIfManuallyShutFromWebClient() {
        SuplaServerMock::mockResponse('GET-VALVE-VALUE', "VALUE:1,2\n");
        $client = $this->createAuthenticatedClient($this->user);
        $client->request('PATCH', '/api/channels/6', [], [], [], json_encode(array_merge(['action' => 'open'])));
        $response = $client->getResponse();
        $this->assertStatusCode('2XX', $response);
    }

    public function testPreventingToOpenValveIfManuallyShutFromApiClient() {
        SuplaServerMock::mockResponse('GET-VALVE-VALUE', "VALUE:1,2\n");
        $client = self::createClient(
            ['debug' => false],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->peronsalToken->getToken(), 'HTTPS' => true]
        );
        $client->request('PATCH', '/api/v2.3.0/channels/6', [], [], [], json_encode(array_merge(['action' => 'open'])));
        $response = $client->getResponse();
        $this->assertStatusCode(409, $response);
        $body = json_decode($response->getContent(), true);
        $this->assertContains('closed manually', $body['message']);
    }

    public function testPreventingToOpenValveIfFloodingFromApiClient() {
        SuplaServerMock::mockResponse('GET-VALVE-VALUE', "VALUE:1,1\n");
        $client = self::createClient(
            ['debug' => false],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->peronsalToken->getToken(), 'HTTPS' => true]
        );
        $client->request('PATCH', '/api/v2.3.0/channels/6', [], [], [], json_encode(array_merge(['action' => 'open'])));
        $response = $client->getResponse();
        $this->assertStatusCode(409, $response);
        $body = json_decode($response->getContent(), true);
        $this->assertContains('closed manually', $body['message']);
    }

    public function testCanOpenValveIfNotManuallyShutFromApiClient() {
        SuplaServerMock::mockResponse('GET-VALVE-VALUE', "VALUE:1,0\n");
        $client = self::createClient(
            ['debug' => false],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->peronsalToken->getToken(), 'HTTPS' => true]
        );
        $client->request('PATCH', '/api/v2.3.0/channels/6', [], [], [], json_encode(array_merge(['action' => 'open'])));
        $response = $client->getResponse();
        $this->assertStatusCode(202, $response);
    }

    public function testResettingCounters() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::IMPULSECOUNTER, ChannelFunction::IC_WATERMETER],
        ]);
        $measurementChannel = $anotherDevice->getChannels()[0];
        EntityUtils::setField($measurementChannel, 'flags', ChannelFunctionBitsFlags::RESET_COUNTERS_ACTION_AVAILABLE);
        $this->getEntityManager()->persist($measurementChannel);
        $this->getEntityManager()->flush();
        $client = $this->createAuthenticatedClient();
        $measurementChannelId = $measurementChannel->getId();
        $client->apiRequestV24('PATCH', "/api/channels/{$measurementChannelId}/settings", [
            'action' => 'resetCounters',
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $this->assertContains("RESET-COUNTERS:1,{$anotherDevice->getId()},{$measurementChannelId}", SuplaServerMock::$executedCommands);
    }

    public function testResettingCountersOfUnsupportedChannel() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEDOORLOCK],
        ]);
        $channel = $anotherDevice->getChannels()[0];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PATCH', "/api/channels/{$channel->getId()}/settings", [
            'action' => 'resetCounters',
        ]);
        $this->assertStatusCode(400, $client->getResponse());
    }

    public function testRecalibrating() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEROLLERSHUTTER],
        ]);
        $channel = $anotherDevice->getChannels()[0];
        EntityUtils::setField($channel, 'flags', ChannelFunctionBitsFlags::RECALIBRATE_ACTION_AVAILABLE);
        $this->getEntityManager()->persist($channel);
        $this->getEntityManager()->flush();
        $client = $this->createAuthenticatedClient();
        $measurementChannelId = $channel->getId();
        $client->apiRequestV24('PATCH', "/api/channels/{$measurementChannelId}/settings", [
            'action' => 'recalibrate',
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $this->assertContains("RECALIBRATE:1,{$anotherDevice->getId()},{$measurementChannelId}", SuplaServerMock::$executedCommands);
    }

    public function testFetchingActionTriggers() {
        $client = $this->createAuthenticatedClient($this->user);
        $anotherDevice = $this->createDeviceSonoff($this->getEntityManager()->find(Location::class, $this->location->getId()));
        $client->apiRequestV24('GET', '/api/channels/' . $anotherDevice->getChannels()[0]->getId() . '?include=actionTriggers');
        $this->assertStatusCode(200, $client->getResponse());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('actionTriggersIds', $content);
        $this->assertEquals([$anotherDevice->getChannels()[2]->getId()], $content['actionTriggersIds']);
    }

    public function testDoesNotFetchOtherParam1AsActionTriggers() {
        $device = $this->createDevice($this->location, [
            [ChannelType::RELAY, ChannelFunction::LIGHTSWITCH],
            [ChannelType::RELAY, ChannelFunction::LIGHTSWITCH],
        ]);
        $firstChannel = $device->getChannels()[0];
        $secondChannel = $device->getChannels()[1];
        $firstChannel->setParam1($secondChannel->getId()); // pretending param1 as AT
        $this->getEntityManager()->persist($firstChannel);
        $this->getEntityManager()->flush();
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('GET', '/api/channels/' . $secondChannel->getId() . '?include=actionTriggers');
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('relationsCount', $content);
        $this->assertEquals(0, $content['relationsCount']['actionTriggers']);
        $this->assertEmpty($content['actionTriggersIds']);
    }

    public function testChangingChannelFunctionClearsRelatedMeasurementChannel() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::LIGHTSWITCH],
            [ChannelType::IMPULSECOUNTER, ChannelFunction::IC_WATERMETER],
        ]);
        $relay = $anotherDevice->getChannels()[0];
        $measurement = $anotherDevice->getChannels()[1];
        $client = $this->createAuthenticatedClient();
        // set measurement channel
        $client->apiRequestV24('PUT', '/api/channels/' . $relay->getId() . '?safe=1', [
            'config' => ['relatedChannelId' => $measurement->getId()],
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $relay = $this->freshEntity($relay);
        $measurement = $this->freshEntity($measurement);
        $this->assertEquals($measurement->getId(), $relay->getParam1());
        $this->assertEquals($relay->getId(), $measurement->getParam4());
        // change relay function
        $client->apiRequestV24('PUT', '/api/channels/' . $relay->getId(), [
            'functionId' => ChannelFunction::CONTROLLINGTHEGATE,
            'config' => ['relatedChannelId' => $measurement->getId()], // old config should not influence the result
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $relay = $this->freshEntity($relay);
        $measurement = $this->freshEntity($measurement);
        $this->assertEquals(500, $relay->getParam1()); // default gate opening time
        $this->assertEquals(0, $measurement->getParam4());
    }

    public function testExecutingActionOnOfflineChannel() {
        SuplaServerMock::mockResponse('ACTION-TURN-ON:1,1,1', "FAIL:1\n");
        $client = $this->createAuthenticatedClient($this->user);
        $client->request('PATCH', '/api/channels/1', [], [], [], json_encode(['action' => ChannelFunctionAction::TURN_ON]));
        $response = $client->getResponse();
        $this->assertStatusCode('400', $response);
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('details', $body);
        $details = $body['details'];
        $this->assertEquals('suplaServerError', $details['error']);
    }

    public function testGettingChannelScenesCount() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEGATEWAYLOCK],
        ]);
        $gateChannel = $anotherDevice->getChannels()[0];
        $scene = new Scene($anotherDevice->getLocation());
        $scene->setOpeartions([
            new SceneOperation($gateChannel, ChannelFunctionAction::OPEN_CLOSE()),
            new SceneOperation($gateChannel, ChannelFunctionAction::OPEN_CLOSE(), [], 100),
        ]);
        $this->persist($scene);
        $client = $this->createAuthenticatedClient($this->user);
        $client->apiRequestV24('GET', '/api/channels/' . $gateChannel->getId());
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('relationsCount', $content);
        $this->assertArrayHasKey('scenes', $content['relationsCount']);
        $this->assertArrayHasKey('sceneOperations', $content['relationsCount']);
        $this->assertEquals(1, $content['relationsCount']['scenes']);
        $this->assertEquals(2, $content['relationsCount']['sceneOperations']);
    }

    public function testSettingNotificationForActionTrigger() {
        $anotherDevice = $this->createDeviceSonoff($this->getEntityManager()->find(Location::class, $this->location->getId()));
        $trigger = $anotherDevice->getChannels()[2];
        $actions = ['TURN_ON' => [
            'subjectType' => ActionableSubjectType::NOTIFICATION,
            'action' => ['id' => ChannelFunctionAction::SEND, 'param' => ['body' => 'ABC', 'accessIds' => [1]]]]];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $trigger->getId(), ['config' => ['actions' => $actions]]);
        $this->assertStatusCode(200, $client->getResponse());
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $trigger->getId());
        $this->assertArrayHasKey('actions', $trigger->getUserConfig());
        $this->assertCount(1, $trigger->getUserConfig()['actions']);
        $notificationId = $trigger->getUserConfigValue('actions')['TURN_ON']['subjectId'];
        $notification = $this->getEntityManager()->find(PushNotification::class, $notificationId);
        $this->assertNotNull($notification);
        $this->assertEquals('ABC', $notification->getBody());
        $this->assertEquals($trigger->getId(), $notification->getChannel()->getId());
        return $trigger->getId();
    }

    /** @depends testSettingNotificationForActionTrigger */
    public function testChangingNotificationForActionTrigger(int $triggerId) {
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $triggerId);
        $previousNotificationId = $trigger->getUserConfigValue('actions')['TURN_ON']['subjectId'];
        $actions = ['TURN_ON' => [
            'subjectType' => ActionableSubjectType::NOTIFICATION,
            'action' => ['id' => ChannelFunctionAction::SEND, 'param' => ['body' => 'DEF', 'accessIds' => [1]]]]];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $trigger->getId(), ['config' => ['actions' => $actions]]);
        $this->assertStatusCode(200, $client->getResponse());
        $trigger = $this->getEntityManager()->find(IODeviceChannel::class, $trigger->getId());
        $this->assertArrayHasKey('actions', $trigger->getUserConfig());
        $this->assertCount(1, $trigger->getUserConfig()['actions']);
        $notificationId = $trigger->getUserConfigValue('actions')['TURN_ON']['subjectId'];
        $this->assertNotEquals($notificationId, $previousNotificationId);
        $notification = $this->getEntityManager()->find(PushNotification::class, $notificationId);
        $this->assertNotNull($notification);
        $this->assertEquals('DEF', $notification->getBody());
        $this->assertNull($this->getEntityManager()->find(PushNotification::class, $previousNotificationId));
    }

    public function testChangingChannelFunctionClearsOwnReactions() {
        $thermometer = $this->device->getChannels()[6];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('POST', "/api/channels/{$thermometer->getId()}/reactions", [
            'subjectType' => ActionableSubjectType::NOTIFICATION,
            'actionId' => ChannelFunctionAction::SEND,
            'actionParam' => ['body' => 'sdf', 'accessIds' => [1]],
            'trigger' => ['on_change_to' => ['lt' => 20, 'name' => 'temperature', 'resume' => ['ge' => 20]]],
        ]);
        $client->apiRequestV24('PUT', '/api/channels/' . $thermometer->getId() . '?safe=1', ['functionId' => ChannelFunction::NONE]);
        $this->assertStatusCode(409, $client->getResponse());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('ownReactions', $content['dependencies']);
        $this->assertCount(1, $content['dependencies']['ownReactions']);
        $client->apiRequestV24('PUT', '/api/channels/' . $thermometer->getId(), ['functionId' => ChannelFunction::NONE]);
        $this->assertStatusCode(200, $client->getResponse());
        $client->apiRequestV24('GET', "/api/channels/{$thermometer->getId()}/reactions");
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertEmpty($content);
    }

    public function testChangingChannelFunctionClearsRelatedReactions() {
        $thermometer = $this->device->getChannels()[6];
        $relay = $this->device->getChannels()[1];
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('PUT', '/api/channels/' . $thermometer->getId() . '?safe=1', ['functionId' => ChannelFunction::THERMOMETER]);
        $this->assertStatusCode(200, $client->getResponse());
        $client->apiRequestV24('POST', "/api/channels/{$thermometer->getId()}/reactions", [
            'subjectId' => $relay->getId(),
            'subjectType' => ActionableSubjectType::CHANNEL,
            'actionId' => ChannelFunctionAction::OPEN,
            'trigger' => ['on_change_to' => ['lt' => 20, 'name' => 'temperature', 'resume' => ['ge' => 20]]],
        ]);
        $this->assertStatusCode(201, $client->getResponse());
        $client->apiRequestV24('PUT', '/api/channels/' . $relay->getId() . '?safe=1', ['functionId' => ChannelFunction::POWERSWITCH]);
        $this->assertStatusCode(409, $client->getResponse());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('reactions', $content['dependencies']);
        $this->assertCount(1, $content['dependencies']['reactions']);
        $client->apiRequestV24('PUT', '/api/channels/' . $relay->getId(), ['functionId' => ChannelFunction::POWERSWITCH]);
        $this->assertStatusCode(200, $client->getResponse());
        $client->apiRequestV24('GET', "/api/channels/{$thermometer->getId()}/reactions");
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertEmpty($content);
    }

    public function testGettingChannelManagedNotificationsCount() {
        $device = $this->createDeviceSonoff($this->getEntityManager()->find(Location::class, $this->location->getId()));
        (new NotificationsFixture())->createDeviceNotification($this->getEntityManager(), $device);
        (new NotificationsFixture())->createChannelNotification($this->getEntityManager(), $device->getChannels()[0]);
        $notManaged = (new NotificationsFixture())->createChannelNotification($this->getEntityManager(), $device->getChannels()[0]);
        EntityUtils::setField($notManaged, 'managedByDevice', false);
        $this->getEntityManager()->persist($notManaged);
        $this->getEntityManager()->flush();
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV24('GET', '/api/channels/' . $device->getChannels()[0]->getId());
        $response = $client->getResponse();
        $this->assertStatusCode(200, $response);
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('managedNotifications', $content['relationsCount']);
        $this->assertEquals(1, $content['relationsCount']['managedNotifications']);
    }

    public function testUpdatingConfigWithComparison() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::THERMOMETER, ChannelFunction::THERMOMETER],
        ]);
        $channelParamConfigTranslator = self::$container->get(SubjectConfigTranslator::class);
        $channel = $anotherDevice->getChannels()[0];
        $channelParamConfigTranslator->setConfig($channel, ['temperatureAdjustment' => 10]);
        $this->getEntityManager()->persist($channel);
        $this->getEntityManager()->flush();
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $channel->getId(), [
            'config' => ['temperatureAdjustment' => 11],
            'configBefore' => ['temperatureAdjustment' => 10],
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $channel = $this->freshEntity($channel);
        $config = $channelParamConfigTranslator->getConfig($channel);
        $this->assertEquals(11, $config['temperatureAdjustment']);
        return $channel->getId();
    }

    /** @depends testUpdatingConfigWithComparison */
    public function testCantUpdateWithoutConfigBefore(int $channelId) {
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $channelId . '?safe=true', [
            'config' => ['temperatureAdjustment' => 11],
        ]);
        $this->assertStatusCode(400, $client->getResponse());
    }

    /** @depends testUpdatingConfigWithComparison */
    public function testCanUpdateCaptionWithoutConfigBefore(int $channelId) {
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $channelId, [
            'caption' => 'Unicorn channel',
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $channel = $this->getEntityManager()->find(IODeviceChannel::class, $channelId);
        $this->assertEquals('Unicorn channel', $channel->getCaption());
    }

    /** @depends testUpdatingConfigWithComparison */
    public function testUpdatingConfigWithConflictingConfigBefore(int $channelId) {
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $channelId, [
            'config' => ['temperatureAdjustment' => 13],
            'configBefore' => ['temperatureAdjustment' => 12],
        ]);
        $this->assertStatusCode(409, $client->getResponse());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('details', $content);
        $this->assertEquals(11, $content['details']['config']['temperatureAdjustment']);
        $this->assertEquals('temperatureAdjustment', $content['details']['conflictingField']);
    }

    /** @depends testUpdatingConfigWithComparison */
    public function testUpdatingConfigWhenBeforeDifferentButCurrentTheSame(int $channelId) {
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $channelId, [
            'config' => ['temperatureAdjustment' => 11],
            'configBefore' => ['temperatureAdjustment' => 12],
        ]);
        $this->assertStatusCode(200, $client->getResponse());
    }

    public function testMergingExternalModifications() {
        $anotherDevice = $this->createDevice($this->getEntityManager()->find(Location::class, $this->location->getId()), [
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEGATE],
        ]);
        $gateChannel = $anotherDevice->getChannels()[0];
        $initialConfig = [
            'relayTimeMs' => 1567,
            'numberOfAttemptsToOpen' => 5,
            'numberOfAttemptsToClose' => 5,
        ];
        $channelParamConfigTranslator = self::$container->get(SubjectConfigTranslator::class);
        $gateChannel = $this->freshEntity($gateChannel);
        $channelParamConfigTranslator->setConfig($gateChannel, $initialConfig);
        $this->getEntityManager()->persist($gateChannel);
        $this->getEntityManager()->flush();
        $this->assertEquals($initialConfig, array_intersect_key($channelParamConfigTranslator->getConfig($gateChannel), $initialConfig));
        // external modification
        $channelParamConfigTranslator->setConfig($gateChannel, ['numberOfAttemptsToOpen' => 3]);
        $this->getEntityManager()->persist($gateChannel);
        $this->getEntityManager()->flush();
        // API modification
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $gateChannel->getId(), [
            'config' => array_replace($initialConfig, ['relayTimeMs' => 999]),
            'configBefore' => $initialConfig,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $gateChannel = $this->freshEntity($gateChannel);
        $this->assertEquals(
            [
                'relayTimeMs' => 999,
                'numberOfAttemptsToOpen' => 3,
                'numberOfAttemptsToClose' => 5,
            ],
            array_intersect_key($channelParamConfigTranslator->getConfig($gateChannel), $initialConfig)
        );
    }

    public function testCannotChangeLocationOfPairedChannelWithoutConfirmation() {
        $channelParamConfigTranslator = self::$container->get(SubjectConfigTranslator::class);
        $deviceLocation = $this->createLocation($this->user);
        $device1 = $this->createDevice($deviceLocation, [
            [ChannelType::SENSORNO, ChannelFunction::OPENINGSENSOR_GATE],
        ]);
        $device2 = $this->createDevice($deviceLocation, [
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEGATE],
        ]);
        $sensorChannel = $device1->getChannels()[0];
        $gateChannel = $device2->getChannels()[0];
        $channelParamConfigTranslator->setConfig($gateChannel, ['openingSensorChannelId' => $sensorChannel->getId()]);
        $this->flush();
        $location = $this->createLocation($this->user);
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $gateChannel->getId() . '?safe=true', [
            'locationId' => $location->getId(),
        ]);
        $this->assertStatusCode(409, $client->getResponse());
        return [$gateChannel->getId(), $sensorChannel->getId(), $location->getId()];
    }

    /** @depends testCannotChangeLocationOfPairedChannelWithoutConfirmation */
    public function testChangingLocationOfDependentChannelsWhenConfirmed($ids) {
        [$gateId, $sensorId, $locationId] = $ids;
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $gateId, [
            'locationId' => $locationId,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $gate = $this->freshEntityById(IODeviceChannel::class, $gateId);
        $sensor = $this->freshEntityById(IODeviceChannel::class, $sensorId);
        $this->assertEquals($locationId, $gate->getLocation()->getId());
        $this->assertEquals($locationId, $sensor->getLocation()->getId());
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,2900,20,%d',
            $gate->getIoDevice()->getId(),
            $gate->getId(),
            ChannelConfigChangeScope::LOCATION
        ));
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,1000,60,%d',
            $sensor->getIoDevice()->getId(),
            $sensor->getId(),
            ChannelConfigChangeScope::LOCATION
        ));
        return $ids;
    }

    /** @depends testChangingLocationOfDependentChannelsWhenConfirmed */
    public function testChangingLocationToInheritedWithDependencies($ids) {
        [$gateId, $sensorId, $locationId] = $ids;
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $gateId, [
            'inheritedLocation' => true,
        ]);
        $this->assertStatusCode(200, $client->getResponse());
        $gate = $this->freshEntityById(IODeviceChannel::class, $gateId);
        $sensor = $this->freshEntityById(IODeviceChannel::class, $sensorId);
        $this->assertNotEquals($locationId, $gate->getLocation()->getId());
        $this->assertEquals($gate->getIoDevice()->getLocation()->getId(), $gate->getLocation()->getId());
        $this->assertEquals($gate->getLocation()->getId(), $sensor->getLocation()->getId());
        $this->assertTrue($gate->hasInheritedLocation());
        $this->assertFalse($sensor->hasInheritedLocation());
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,2900,20,%d',
            $gate->getIoDevice()->getId(),
            $gate->getId(),
            ChannelConfigChangeScope::LOCATION
        ));
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,1000,60,%d',
            $sensor->getIoDevice()->getId(),
            $sensor->getId(),
            ChannelConfigChangeScope::LOCATION
        ));
    }

    public function testCannotChangeHiddenOfPairedChannelWithoutConfirmation() {
        $channelParamConfigTranslator = self::$container->get(SubjectConfigTranslator::class);
        $deviceLocation = $this->createLocation($this->user);
        $device1 = $this->createDevice($deviceLocation, [
            [ChannelType::SENSORNO, ChannelFunction::OPENINGSENSOR_GATE],
        ]);
        $device2 = $this->createDevice($deviceLocation, [
            [ChannelType::RELAY, ChannelFunction::CONTROLLINGTHEGATE],
        ]);
        $sensorChannel = $device1->getChannels()[0];
        $gateChannel = $device2->getChannels()[0];
        $channelParamConfigTranslator->setConfig($gateChannel, ['openingSensorChannelId' => $sensorChannel->getId()]);
        $this->flush();
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $gateChannel->getId() . '?safe=true', ['hidden' => true]);
        $this->assertStatusCode(409, $client->getResponse());
        $content = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('hidden', $content['conflictOn']);
        $this->assertCount(1, $content['dependencies']['channels']);
        $this->assertEquals($sensorChannel->getId(), $content['dependencies']['channels'][0]['id']);
        return [$gateChannel->getId(), $sensorChannel->getId()];
    }

    /** @depends testCannotChangeHiddenOfPairedChannelWithoutConfirmation */
    public function testChangingHiddenOfDependentChannelsWhenConfirmed($ids) {
        [$gateId, $sensorId] = $ids;
        $sensor = $this->freshEntityById(IODeviceChannel::class, $sensorId);
        $this->assertFalse($sensor->getHidden());
        $client = $this->createAuthenticatedClient();
        $client->apiRequestV3('PUT', '/api/channels/' . $gateId, ['hidden' => true]);
        $this->assertStatusCode(200, $client->getResponse());
        $gate = $this->freshEntityById(IODeviceChannel::class, $gateId);
        $sensor = $this->freshEntityById(IODeviceChannel::class, $sensorId);
        $this->assertTrue($gate->getHidden());
        $this->assertTrue($sensor->getHidden());
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,2900,20,%d',
            $gate->getIoDevice()->getId(),
            $gate->getId(),
            ChannelConfigChangeScope::VISIBILITY
        ));
        $this->assertSuplaCommandExecuted(sprintf(
            'USER-ON-CHANNEL-CONFIG-CHANGED:1,%d,%d,1000,60,%d',
            $sensor->getIoDevice()->getId(),
            $sensor->getId(),
            ChannelConfigChangeScope::VISIBILITY
        ));
    }
}
