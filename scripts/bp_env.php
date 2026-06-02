<?php

if( ! function_exists( 'bp_env' ) ) {

    function bp_env( string $key, mixed $default = null ): mixed {

        $value = getenv( $key );

        if( $value === false || $value === '' ) {

            return $default;
        }

        return $value;
    }
}

if( ! function_exists( 'bp_env_bool' ) ) {

    function bp_env_bool( string $key, bool $default = false ): bool {

        $value = bp_env( $key, null );

        if( $value === null ) {

            return $default;
        }

        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }
}
