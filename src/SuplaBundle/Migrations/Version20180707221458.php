<?php

namespace SuplaBundle\Migrations;

use SuplaBundle\Enums\ApiClientType;

/**
 * Set all existing API clients to USER type.
 */
class Version20180707221458 extends NoWayBackMigration {
    public function migrate() {
        $this->addSql('UPDATE supla_oauth_clients SET type = ' . ApiClientType::USER);
    }
}