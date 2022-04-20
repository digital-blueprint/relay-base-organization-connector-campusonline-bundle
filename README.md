# DbpRelayBaseOrganizationConnectorCampusonlineBundle
[GitLab](https://gitlab.tugraz.at/dbp/relay/dbp-relay-base-organization-connector-campusonline-bundle)


## Integration into the Relay API Server

* Add the bundle package as a dependency:

```
composer require dbp/relay-base-organization-connector-campusonline-bundle
```

* Add the bundle to your `config/bundles.php`:

```php
...
Dbp\Relay\BasePersonBundle\DbpRelayBaseOrganizationBundle::class => ['all' => true],
Dbp\Relay\BasePersonBundle\DbpRelayBaseOrganizationConnectorCampusonlineBundle::class => ['all' => true],
...
```

* Run `composer install` to clear caches

## Configuration

The bundle has some configuration values that you can specify in your
app, either by hard-coding it, or by referencing an environment variable.

For this create `config/packages/dbp_relay_base_organization_connector_ldap.yaml` in the app with the following
content:

```yaml
dbp_relay_base_organization_connector_campusonline:
  campus_online:
    api_token: '%env(CAMPUS_ONLINE_API_TOKEN)%'
    api_url: '%env(CAMPUS_ONLINE_API_URL)%'
    org_root_id: '%env(ORGANIZATION_ROOT_ID)%'
```

For more info on bundle configuration see
https://symfony.com/doc/current/bundles/configuration.html

## Events

### OrganizationProviderPostEvent

This event allows you to add additional attributes ("local data") to the `\Dbp\Relay\BaseOrganizationBundle\Entity\Organization` base-entity that you want to be included in responses to `Organization` entity requests.
Event subscribers receive a `\Dbp\Relay\RelayBaseOrganizationConnectorCampusonlineBundle\Event\OrganizationProviderPostEvent` instance containing the `Organization` base-entity and the organization data provided by Campusonline. Additional attributes are stored in the `localData`-map of the base-entity.

For example, create an event subscriber `src/EventSubscriber/OrganizationProviderSubscriber.php`:

```php
<?php
namespace App\EventSubscriber;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrganizationProviderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            OrganizationPostEvent::NAME => 'onPost',
    ];
    }

    public function onPost(OrganizationPostEvent $event)
    {
        $organization = $event->getOrganization();
        $organizationData = $event->getOrganizationUnitData();
        $organization->setLocalDataValue('code', $organizationData->getCode());
    }
}
```

And add it to your `src/Resources/config/services.yaml`:

```yaml
App\EventSubscriber\OrganizationProviderSubscriber:
  autowire: true
  autoconfigure: true
```