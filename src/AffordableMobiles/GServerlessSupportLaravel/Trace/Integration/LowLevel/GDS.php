<?php

declare(strict_types=1);

namespace AffordableMobiles\GServerlessSupportLaravel\Trace\Integration\LowLevel;

use GDS\Gateway\GRPCv1 as Gateway;
use GDS\Gateway\RESTv1 as GatewayREST;
use OpenCensus\Trace\Integrations\IntegrationInterface;
use OpenCensus\Trace\Span;

class GDS implements IntegrationInterface
{
    /**
     * Static method to add instrumentation.
     */
    public static function load(): void
    {
        if (!\extension_loaded('opencensus')) {
            trigger_error('opencensus extension required to load GDS (Datastore) integrations.', E_USER_WARNING);

            return;
        }

        self::load_REST();

        self::load_gRPC();
    }

    public static function load_REST(): void
    {
        opencensus_trace_method(GatewayREST::class, 'executePostRequest', static function ($str_method, $args) {
            return [
                'name'       => 'GDS::execute/'.$str_method,
                'attributes' => [],
                'kind'       => Span::KIND_CLIENT,
            ];
        });

        opencensus_trace_method(GatewayREST::class, 'fetchByKeyPart', static function ($arr_key_parts, $str_setter) {
            return [
                'name'       => 'GDS::fetchByKeyPart',
                'attributes' => [],
                'kind'       => Span::KIND_CLIENT,
            ];
        });

        opencensus_trace_method(GatewayREST::class, 'upsert', static function ($arr_key_parts, $str_setter) {
            return [
                'name'       => 'GDS::upsert',
                'attributes' => [],
                'kind'       => Span::KIND_CLIENT,
            ];
        });

        opencensus_trace_method(GatewayREST::class, 'gql', static function ($arr_key_parts, $str_setter) {
            return [
                'name'       => 'GDS::gql',
                'attributes' => [],
                'kind'       => Span::KIND_CLIENT,
            ];
        });
    }

    public static function load_gRPC(): void
    {
        opencensus_trace_method(Gateway::class, 'execute', static function ($str_method, $args) {
            return [
                'name'       => 'GDS::execute/'.$str_method,
                'attributes' => [],
                'kind'       => Span::KIND_CLIENT,
            ];
        });

        opencensus_trace_method(Gateway::class, 'fetchByKeyPart', static function ($arr_key_parts, $str_setter) {
            return [
                'name'       => 'GDS::fetchByKeyPart',
                'attributes' => [],
                'kind'       => Span::KIND_CLIENT,
            ];
        });

        opencensus_trace_method(Gateway::class, 'upsert', static function ($arr_key_parts, $str_setter) {
            return [
                'name'       => 'GDS::upsert',
                'attributes' => [],
                'kind'       => Span::KIND_CLIENT,
            ];
        });

        opencensus_trace_method(Gateway::class, 'gql', static function ($arr_key_parts, $str_setter) {
            return [
                'name'       => 'GDS::gql',
                'attributes' => [],
                'kind'       => Span::KIND_CLIENT,
            ];
        });
    }
}
