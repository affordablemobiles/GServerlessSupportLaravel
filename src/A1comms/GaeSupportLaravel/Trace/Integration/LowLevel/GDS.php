<?php

namespace A1comms\GaeSupportLaravel\Trace\Integration\LowLevel;

use GDS\Gateway\GRPCv1 as Gateway;
use GDS\Gateway\RESTv1 as GatewayREST;
use OpenCensus\Trace\Span;
use OpenCensus\Trace\Tracer;
use OpenCensus\Trace\Integrations\IntegrationInterface;

class GDS implements IntegrationInterface
{
    /**
     * Static method to add instrumentation
     */
    public static function load()
    {
        if (!extension_loaded('opencensus')) {
            trigger_error('opencensus extension required to load GDS (Datastore) integrations.', E_USER_WARNING);
            return;
        }

        self::load_REST();

        self::load_gRPC();
    }

    public static function load_REST()
    {
        opencensus_trace_method(GatewayREST::class, 'executePostRequest', function ($str_method, $args) {
            return [
                'name' => 'GDS::execute/'.$str_method,
                'attributes' => [],
                'kind' => Span::KIND_CLIENT
            ];
        });

        opencensus_trace_method(GatewayREST::class, 'fetchByKeyPart', function ($arr_key_parts, $str_setter) {
            return [
                'name' => 'GDS::fetchByKeyPart',
                'attributes' => [],
                'kind' => Span::KIND_CLIENT
            ];
        });

        opencensus_trace_method(GatewayREST::class, 'upsert', function ($arr_key_parts, $str_setter) {
            return [
                'name' => 'GDS::upsert',
                'attributes' => [],
                'kind' => Span::KIND_CLIENT
            ];
        });

        opencensus_trace_method(GatewayREST::class, 'gql', function ($arr_key_parts, $str_setter) {
            return [
                'name' => 'GDS::gql',
                'attributes' => [],
                'kind' => Span::KIND_CLIENT
            ];
        });
    }

    public static function load_gRPC()
    {
        opencensus_trace_method(Gateway::class, 'execute', function ($str_method, $args) {
            return [
                'name' => 'GDS::execute/'.$str_method,
                'attributes' => [],
                'kind' => Span::KIND_CLIENT
            ];
        });

        opencensus_trace_method(Gateway::class, 'fetchByKeyPart', function ($arr_key_parts, $str_setter) {
            return [
                'name' => 'GDS::fetchByKeyPart',
                'attributes' => [],
                'kind' => Span::KIND_CLIENT
            ];
        });

        opencensus_trace_method(Gateway::class, 'upsert', function ($arr_key_parts, $str_setter) {
            return [
                'name' => 'GDS::upsert',
                'attributes' => [],
                'kind' => Span::KIND_CLIENT
            ];
        });

        opencensus_trace_method(Gateway::class, 'gql', function ($arr_key_parts, $str_setter) {
            return [
                'name' => 'GDS::gql',
                'attributes' => [],
                'kind' => Span::KIND_CLIENT
            ];
        });
    }
}
