<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Log;

use Google\Cloud\Core\Compute\Metadata;
use Google\Cloud\Core\Report\EmptyMetadataProvider;
use Google\Cloud\Core\Report\MetadataProviderInterface;

class MetadataProvider implements MetadataProviderInterface
{
    public static function instance(): self
    {
        if (is_g_serverless()) {
            return new self();
        }

        return new EmptyMetadataProvider();
    }

    /**
     * Return an array representing MonitoredResource.
     *
     * @see https://cloud.google.com/logging/docs/reference/v2/rest/v2/MonitoredResource
     *
     * @return array
     */
    public function monitoredResource()
    {
        return once(static function (): array {
            if (is_gae_std()) {
                return [
                    'type'   => 'gae_app',
                    'labels' => [
                        'project_id' => g_project(),
                        'version_id' => g_version(),
                        'module_id'  => g_service(),
                    ],
                ];
            }
            if (is_cloud_run()) {
                $location = explode('/', (new Metadata())->get('instance/region'));

                return [
                    'type'   => 'cloud_run_revision',
                    'labels' => [
                        'configuration_name' => $_SERVER['K_CONFIGURATION'],
                        'location'           => array_pop($location),
                        'project_id'         => g_project(),
                        'revision_name'      => g_version(),
                        'service_name'       => g_service(),
                    ],
                ];
            }

            return [];
        });
    }

    /**
     * Return the project id.
     *
     * @return string
     */
    public function projectId()
    {
        return g_project();
    }

    /**
     * Return the service id.
     *
     * @return string
     */
    public function serviceId()
    {
        return g_service();
    }

    /**
     * Return the version id.
     *
     * @return string
     */
    public function versionId()
    {
        return g_version();
    }

    /**
     * Return the labels.
     *
     * @return array
     */
    public function labels()
    {
        return [
            (is_gae_std() ? 'appengine.googleapis.com/trace_id' : 'run.googleapis.com/trace_id') => g_serverless_trace_id(),
        ];
    }
}
