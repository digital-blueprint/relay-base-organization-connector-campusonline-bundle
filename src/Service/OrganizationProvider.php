<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\Relay\BaseOrganizationBundle\API\OrganizationProviderInterface;
use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BasePersonBundle\Entity\Person;
use Dbp\Relay\CoreBundle\Exception\ApiError;

class OrganizationProvider implements OrganizationProviderInterface
{
    private const HTTP_STATUS_NOT_FOUND = 404;
    private const LANGUAGE_OPTION_NAME = 'lang';
    private const DEFAULT_LANGUAGE = 'de';

    /*
     * @var OrganizationApi
     */
    private $orgApi;

    public function __construct(OrganizationApi $orgApi)
    {
        $this->orgApi = $orgApi;
    }

    /**
     * TODO: change return type to ?Organization. Kept Organization because of ALMA code depending on non-null return type.
     *
     * @throws ApiError
     */
    public function getOrganizationById(string $identifier, string $lang): Organization
    {
        try {
            $org = $this->orgApi->getOrganizationById($identifier, self::makeOptions($lang));
        } catch (\Exception $e) {
            throw new ApiError($e->getCode(), $e->getMessage());
        }

        if ($org === null) {
            throw new ApiError(self::HTTP_STATUS_NOT_FOUND, 'orgainization unit with ID '.$identifier.' not found');
        }

        return $org;
    }

    /**
     * currently not supported by the Campusonline API.
     *
     * @return Organization[]
     *
     * @throws ApiError
     */
    public function getOrganizationsByPerson(Person $person, string $context, string $lang): array
    {
        return [];
    }

    /**
     * @return Organization[]
     *
     * @throws ApiError
     */
    public function getOrganizations(string $lang): array
    {
        try {
            return $this->orgApi->getOrganizations(self::makeOptions($lang));
        } catch (\Exception $e) {
            throw new ApiError($e->getCode(), $e->getMessage());
        }
    }

    private static function makeOptions(string $lang): array
    {
        return [self::LANGUAGE_OPTION_NAME => ($lang !== '' ? $lang : self::DEFAULT_LANGUAGE)];
    }
}
