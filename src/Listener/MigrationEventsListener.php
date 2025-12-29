<?php

declare(strict_types=1);

namespace Marshal\Database\Listener;

use Doctrine\DBAL\Schema\Schema as DBALSchema;
use Marshal\Database\Type;
use Marshal\Database\TypeManager;
use Marshal\Database\Event\GenerateMigrationEvent;
use Marshal\Database\Event\RollbackMigrationEvent;
use Marshal\Database\Event\RunMigrationEvent;
use Marshal\Database\Event\SaveMigrationEvent;
use Marshal\Database\Event\SetupMigrationsEvent;
use Marshal\Database\Query;
use Marshal\Database\Schema\Migration;
use Marshal\Database\DatabaseManager;
use Marshal\Utils\Logger\LoggerManager;
use Marshal\Utils\Schema;

final class MigrationEventsListener
{
    public function __construct(private array $schemaConfig, private array $databaseConfig)
    {
    }

    public function onGenerateMigrationEvent(GenerateMigrationEvent $event): void
    {
        $database = $event->getDatabase();

        // gather the definitions
        $definitions = [];

        foreach ($this->schemaConfig['types'] ?? [] as $name => $typeConfig) {
            if (! isset($typeConfig['database']) || $typeConfig['database'] !== $database) {
                continue;
            }

            $definitions[$name] = TypeManager::get($name);
        }

        // generate the schema diff
        $dbalSchema = DatabaseManager::getConnection($database)->createSchemaManager();
        $fromSchema = $dbalSchema->introspectSchema();
        $toSchema = $this->buildContentSchema($definitions);
        $diff = $dbalSchema->createComparator()->compareSchemas($fromSchema, $toSchema);
        $event->setDiff($diff);
    }

    public function onRollbackMigrationEvent(RollbackMigrationEvent $event): void
    {
    }

    public function onRunMigrationEvent(RunMigrationEvent $event): void
    {
    }

    public function onSaveMigrationEvent(SaveMigrationEvent $event): void
    {
        try {
            Query::schema(Migration::SCHEMA_NAME)
                ->values([
                    Schema::PROPERTY_NAME => $event->getMigrationName(),
                    Migration::PROPERTY_DATABASE => $event->getDatabase(),
                    Migration::PROPERTY_DIFF => \serialize($event->getSchemaDiff()),
                ])
                ->create();
            $event->setIsSuccess(TRUE);
        } catch (\Throwable $e) {
            LoggerManager::get()->error($e->getMessage());
        }
    }

    public function onSetupMigrationsEvent(SetupMigrationsEvent $event): void
    {
    }

    private function buildContentSchema(array $definition): DBALSchema
    {
        $schema = new DBALSchema();
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
                // @todo handle exception thrown here
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
                        foreignTableName: $relation->getTable(),
                        localColumnNames: [$property->getName()],
                        foreignColumnNames: [$relation->getRelationProperty()->getName()],
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
