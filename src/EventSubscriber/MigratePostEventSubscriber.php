<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\DB\MigratePostEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class MigratePostEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MigratePostEvent::class => 'onMigratePostEvent',
        ];
    }

    public function __construct(
        private OrganizationProvider $organizationProvider)
    {
    }

    public function onMigratePostEvent(MigratePostEvent $event): void
    {
        $output = $event->getOutput();
        try {
            // only recreate cache if it is empty
            if (empty($this->organizationProvider->getOrganizations(1, 1))) {
                $output->writeln('Initializing base organization cache...');
                $this->organizationProvider->recreateOrganizationsCache();
            }
        } catch (\Throwable $throwable) {
            $output->writeln('Error initializing base organization cache: '.$throwable->getMessage());
        }
    }
}
