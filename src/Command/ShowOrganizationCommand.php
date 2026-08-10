<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Command;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationName;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dbp:relay:base-organization-connector-campusonline:show-organization',
    description: 'Show all cached information for a specific organization ID',
)]
class ShowOrganizationCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('identifier', InputArgument::REQUIRED, 'The organization UID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = $input->getArgument('identifier');

        $cachedOrganization = $this->entityManager->getRepository(CachedOrganization::class)->find($identifier);

        if ($cachedOrganization === null) {
            $io->error(sprintf("Organization '%s' not found in cache.", $identifier));

            return Command::FAILURE;
        }

        $io->title(sprintf('Organization: %s', $identifier));

        // uid (from BASE_ENTITY_ATTRIBUTE_MAPPING)
        $rows = [
            ['identifier', $cachedOrganization->getUid() ?? ''],
        ];

        // all fields from LOCAL_DATA_SOURCE_ATTRIBUTES (uid, code, parentUid, groupKey, typeUid)
        foreach (CachedOrganization::LOCAL_DATA_SOURCE_ATTRIBUTES as $key => $getter) {
            $rows[] = [$key, (string) ($cachedOrganization->$getter() ?? '')];
        }

        // the localized names
        /** @var CachedOrganizationName $cachedOrganizationName */
        foreach ($cachedOrganization->getNames() as $cachedOrganizationName) {
            $rows[] = [
                sprintf('name (%s)', $cachedOrganizationName->getLanguageTag() ?? ''),
                $cachedOrganizationName->getName() ?? '',
            ];
        }

        $io->table(['Key', 'Value'], $rows);

        return Command::SUCCESS;
    }
}
