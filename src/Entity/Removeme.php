<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Controller\LoggedInOnly;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     collectionOperations={
 *         "get" = {
 *             "path" = "/base-organization-connector-campusonline/removemes",
 *             "openapi_context" = {
 *                 "tags" = {"Base Organization Connector for the Campusonline API"},
 *             },
 *         }
 *     },
 *     itemOperations={
 *         "get" = {
 *             "path" = "/base-organization-connector-campusonline/removemes/{identifier}",
 *             "openapi_context" = {
 *                 "tags" = {"Base Organization Connector for the Campusonline API"},
 *             },
 *         },
 *         "put" = {
 *             "path" = "/base-organization-connector-campusonline/removemes/{identifier}",
 *             "openapi_context" = {
 *                 "tags" = {"Base Organization Connector for the Campusonline API"},
 *             },
 *         },
 *         "delete" = {
 *             "path" = "/base-organization-connector-campusonline/removemes/{identifier}",
 *             "openapi_context" = {
 *                 "tags" = {"Base Organization Connector for the Campusonline API"},
 *             },
 *         },
 *         "loggedin_only" = {
 *             "security" = "is_granted('IS_AUTHENTICATED_FULLY')",
 *             "method" = "GET",
 *             "path" = "/base-organization-connector-campusonline/removemes/{identifier}/loggedin-only",
 *             "controller" = LoggedInOnly::class,
 *             "openapi_context" = {
 *                 "summary" = "Only works when logged in.",
 *                 "tags" = {"Base Organization Connector for the Campusonline API"},
 *             },
 *         }
 *     },
 *     iri="https://schema.org/Removeme",
 *     shortName="BaseOrganizationConnectorCampusonlineRemoveme",
 *     normalizationContext={
 *         "groups" = {"BaseOrganizationConnectorCampusonlineRemoveme:output"},
 *         "jsonld_embed_context" = true
 *     },
 *     denormalizationContext={
 *         "groups" = {"BaseOrganizationConnectorCampusonlineRemoveme:input"},
 *         "jsonld_embed_context" = true
 *     }
 * )
 */
class Removeme
{
    /**
     * @ApiProperty(identifier=true)
     */
    private $identifier;

    /**
     * @ApiProperty(iri="https://schema.org/name")
     * @Groups({"BaseOrganizationConnectorCampusonlineRemoveme:output", "BaseOrganizationConnectorCampusonlineRemoveme:input"})
     *
     * @var string
     */
    private $name;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }
}
