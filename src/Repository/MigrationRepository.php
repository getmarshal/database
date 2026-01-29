<?php

declare(strict_types=1);

namespace Marshal\Database\Repository;

use Marshal\Database\ConfigProvider;
use Marshal\Database\Query;
use Marshal\Database\Schema\Type;

final class MigrationRepository
{
    public static function save(array $input): Type
    {
        return Query::create(ConfigProvider::MIGRATION_TYPE)
            ->fromInput($input)
            ->execute();
    }
}
