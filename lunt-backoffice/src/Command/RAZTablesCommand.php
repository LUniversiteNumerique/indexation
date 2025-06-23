<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
  name: 'raz:all-data',
  description: 'Vide toutes les tables',
)]
class RAZTablesCommand extends Command
{
  public function __construct(private readonly EntityManagerInterface $em)
  {
    parent::__construct();
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $connection = $this->em->getConnection();
    $platform = $connection->getDatabasePlatform();

    $io->title('RAZ de toutes les tables Doctrine');

    // Désactive les contraintes de clés étrangères
    $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0;');

    // Récupère toutes les tables Doctrine
    $metaData = $this->em->getMetadataFactory()->getAllMetadata();
    foreach ($metaData as $meta) {
      $table = $meta->getTableName();
      $sql = $platform->getTruncateTableSQL($table, true);
      $connection->executeStatement($sql);
      $io->success("Table {$table} vidée !");
    }

    // Vide aussi les tables de jointure ManyToMany
    $jointureTables = [
      'dewey_group_dewey',
      'discipline_group_specialites',
    ];
    foreach ($jointureTables as $table) {
      $sql = $platform->getTruncateTableSQL($table, true);
      $connection->executeStatement($sql);
      $io->success("Table de jointure {$table} vidée !");
    }

    // Réactive les contraintes
    $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1;');

    $io->success('Toutes les tables Doctrine ont été vidées !');
    return Command::SUCCESS;
  }
}
