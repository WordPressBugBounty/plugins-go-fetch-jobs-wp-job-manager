<?php
/**
 * Based on the work by: Giuseppe Mazzapica (https://gm.zoomlab.it)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface BC_Framework_Pointers_Manager_Interface {

    /**
     * Load pointers and setup id with prefix and version.
     * Cast pointers to objects.
     */
    public function parse();

    /**
     * Remove from parse pointers dismissed ones and pointers
     * that should not be shown on given page
     *
     * @param string $page Current admin page file
     */
    public function filter( $page );

}
