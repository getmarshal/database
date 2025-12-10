<?php

declare(strict_types= 1);

namespace Marshal\Utils\Database\Migration;

use Doctrine\DBAL\Schema\Schema;
use Marshal\Utils\Database\Schema\Type;

trait MigrationCommandTrait
{
    private function buildContentSchema(array $definition): Schema
    {
        $schema = new Schema();
        foreach ($definition as $type) {
            if (! $type instanceof Type) {
                continue;
            }

            $table = $schema->createTable($type->getTable());
            foreach ($type->getProperties() as $property) {
                // prepare column options
                $columnOptions = [
                    'notnull' => $property->getNotNull(),
                    'default' => $property->getDefaultValue(),
                    'autoincrement' => $property->isAutoIncrement(),
                    'length' => $property->getLength(),
                    'fixed' => $property->getFixed(),
                    'precision' => $property->getPrecision(),
                    'scale' => $property->getScale(),
                    'platformOptions' => $property->getPlatformOptions(),
                    'unsigned' => $property->getUnsigned(),
                ];

                if ($property->hasDescription()) {
                    $columnOptions['comment'] = $property->getDescription();
                }

                // add column to table
                $table->addColumn(
                    name: $property->getName(),
                    typeName: $property->getDatabaseTypeName(),
                    options: $columnOptions
                );

                // autoincrementing properties are primary keys
                if ($property->isAutoIncrement()) {
                    $table->setPrimaryKey([$property->getName()]);
                }

                // configure column index
                if ($property->hasIndex()) {
                    $table->addIndex(
                        columnNames: [$property->getName()],
                        indexName: $property->getIndex()->getName() ?? \strtolower("idx_{$type->getTable()}_{$property->getName()}"),
                        flags: $property->getIndex()->getFlags(),
                        options: $property->getIndex()->getOptions()
                    );
                }

                if ($property->hasUniqueConstraint()) {
                    $constraint = $property->getUniqueConstraint();
                    $table->addUniqueIndex(
                        columnNames: [$property->getName()],
                        indexName: $constraint->getName() ?? \strtolower("uniq_{$type->getTable()}_{$property->getName()}"),
                        options: $constraint->getOptions(),
                    );
                }

                // configure column foreign key
                if ($property->hasRelation()) {
                    $relation = $property->getRelation();
                    $table->addForeignKeyConstraint(
                        foreignTableName: $relation->getType()->getTable(),
                        localColumnNames: [$property->getName()],
                        foreignColumnNames: [$relation->getProperty()->getName()],
                        options: [
                            'onUpdate' => $relation->getOnUpdate(),
                            'onDelete' => $relation->getOnDelete(),
                        ],
                        name: \strtolower("fk_{$type->getTable()}_{$property->getName()}")
                    );
                }
            }
        }

        return $schema;
    }
}
