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

namespace SuplaBundle\Entity\Main;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use SuplaBundle\Entity\BelongsToUser;
use SuplaBundle\Entity\HasLocation;
use SuplaBundle\Entity\HasRelationsCount;
use SuplaBundle\Entity\HasRelationsCountTrait;
use SuplaBundle\Entity\HasUserConfigTrait;
use SuplaBundle\Entity\Main\Listeners\IODeviceEntityListener;
use SuplaBundle\Enums\IoDeviceFlags;
use SuplaBundle\Enums\Manufacturer;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * @ORM\Entity(repositoryClass="SuplaBundle\Repository\IODeviceRepository")
 * @ORM\EntityListeners({IODeviceEntityListener::class})
 * @ORM\Table(name="supla_iodevice", uniqueConstraints={@UniqueConstraint(name="UNIQUE_USER_GUID", columns={"user_id", "guid"})})
 */
class IODevice implements HasLocation, HasRelationsCount {
    use BelongsToUser;
    use HasRelationsCountTrait;
    use HasUserConfigTrait;

    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"basic"})
     */
    private $id;

    /**
     * @ORM\Column(name="guid", type="binary", length=16, nullable=false)
     */
    private $guid;

    /**
     * @ORM\Column(name="name", type="string", length=100, nullable=true)
     * @Groups({"basic"})
     */
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity="Location", inversedBy="ioDevices")
     * @ORM\JoinColumn(name="location_id", referencedColumnName="id", nullable=false)
     * @Groups({"iodevice.location"})
     * @MaxDepth(1)
     */
    private $location;

    /**
     * @ORM\ManyToOne(targetEntity="Location", inversedBy="ioDevices_ol")
     * @ORM\JoinColumn(name="original_location_id", referencedColumnName="id", nullable=true)
     * @Groups({"iodevice.originalLocation"})
     * @MaxDepth(1)
     */
    private $originalLocation;

    /**
     * @var IODeviceChannel[]
     * @ORM\OneToMany(targetEntity="IODeviceChannel", mappedBy="iodevice")
     * @Groups({"iodevice.channels"})
     * @MaxDepth(1)
     */
    private $channels;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="iodevices")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=false)
     */
    private $user;

    /**
     * @var PushNotification[]
     * @ORM\OneToMany(targetEntity="PushNotification", mappedBy="device", cascade={"remove"})
     */
    private $pushNotifications;

    /**
     * @ORM\Column(name="enabled", type="boolean", nullable=false)
     * @Groups({"basic"})
     */
    private $enabled = true;

    /**
     * @ORM\Column(name="comment", type="string", length=200, nullable=true)
     * @Groups({"basic"})
     */
    private $comment;

    /**
     * @ORM\Column(name="reg_date", type="utcdatetime")
     * @Groups({"basic"})
     */
    private $regDate;

    /**
     * @ORM\Column(name="reg_ipv4", type="ipaddress", nullable=true, options={"unsigned"=true})
     * @Groups({"basic"})
     */
    private $regIpv4;

    /**
     * @ORM\Column(name="last_connected", type="utcdatetime", nullable=true)
     * @Groups({"basic"})
     */
    private $lastConnected;

    /**
     * @ORM\Column(name="last_ipv4", type="ipaddress", nullable=true, options={"unsigned"=true})
     * @Groups({"basic"})
     */
    private $lastIpv4;

    /**
     * @ORM\Column(name="software_version", type="string", length=20, nullable=false)
     * @Groups({"basic"})
     */
    private $softwareVersion;

    /**
     * @ORM\Column(name="protocol_version", type="integer", nullable=false)
     */
    private $protocolVersion;

    /**
     * @ORM\Column(name="auth_key", type="string", length=64, nullable=true)
     */
    private $authKey;

    /**
     * @ORM\Column(name="flags", type="integer", nullable=true)
     */
    private $flags = 0;

    /**
     * @ORM\Column(name="manufacturer_id", type="smallint", nullable=true)
     * @Groups({"basic"})
     */
    private $manufacturer;

    /**
     * @ORM\Column(name="product_id", type="smallint", nullable=true)
     * @Groups({"basic"})
     */
    private $productId;

    /** @ORM\Column(name="user_config", type="string", length=4096, nullable=true) */
    private $userConfig;

    /** @ORM\Column(name="properties", type="string", length=2048, nullable=true) */
    private $properties;

    public function __construct() {
        $this->channels = new ArrayCollection();
        $this->pushNotifications = new ArrayCollection();
    }

    public function getEnabled() {
        return $this->enabled;
    }

    public function setEnabled($enabled) {
        $this->enabled = $enabled;
    }

    public function getComment() {
        return $this->comment;
    }

    public function setComment($comment) {
        $this->comment = $comment;
    }

    public function getName() {
        return $this->name;
    }

    public function getLocation(): Location {
        return $this->location;
    }

    public function setLocation(Location $location) {
        $this->location = $location;
    }

    /** @return Location|null */
    public function getOriginalLocation() {
        return $this->originalLocation;
    }

    /** @return Collection|IODeviceChannel[] */
    public function getChannels(): Collection {
        return $this->channels;
    }

    public function getUser(): User {
        return $this->user;
    }

    public function getRegDate() {
        return $this->regDate;
    }

    /** @return null|string */
    public function getRegIpv4() {
        return $this->regIpv4;
    }

    public function getLastConnected() {
        return $this->lastConnected;
    }

    /** @return null|string */
    public function getLastIpv4() {
        return $this->lastIpv4;
    }

    public function getId() {
        return $this->id;
    }

    public function getGUID() {
        $guid = $this->guid;
        if (get_resource_type($guid) == 'stream') {
            $guid = bin2hex(stream_get_contents($guid, -1, 0));
        };
        return $guid;
    }

    /** @Groups({"basic"}) */
    public function getGUIDString(): string {
        $guid = $this->getGUID();
        return strtoupper(substr($guid, 0, 8) . '-'
            . substr($guid, 8, 4) . '-'
            . substr($guid, 12, 4) . '-'
            . substr($guid, 16, 4) . '-'
            . substr($guid, 20, 12));
    }

    public function getSoftwareVersion() {
        return $this->softwareVersion;
    }

    public function getProtocolVersion() {
        return $this->protocolVersion;
    }

    public function getManufacturer(): Manufacturer {
        return new Manufacturer($this->manufacturer);
    }

    public function getProductId(): ?int {
        return $this->productId;
    }

    /** @Groups({"basic"}) */
    public function isEnterConfigurationModeAvailable(): bool {
        return IoDeviceFlags::ENTER_CONFIGURATION_MODE_AVAILABLE()->isSupported($this->flags);
    }

    /** @Groups({"basic"}) */
    public function isSleepModeEnabled(): bool {
        return IoDeviceFlags::SLEEP_MODE_ENABLED()->isSupported($this->flags);
    }

    /** @return Collection|PushNotification[] */
    public function getPushNotifications(): Collection {
        $criteria = Criteria::create();
        $criteria->where(Criteria::expr()->isNull('channel'));
        return $this->pushNotifications->matching($criteria);
    }

    public function getProperties(): array {
        return $this->properties ? (json_decode($this->properties, true) ?: []) : [];
    }
}
