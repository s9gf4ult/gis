<?php
/**
 *  Support the "Map sources" list mechanism, see also:
 *  http://en.wikipedia.org/wiki/Wikipedia:WikiProject_Geographical_coordinates
 *
 *  This extension was designed to work together with the geo tag
 *  extension (geo.php). It can be useful in its own right also, but
 *  class GeoParam from geo.php needs to be avalibale
 *
 *  To install, remember to tune the settings in "gissettings.php".
 *
 *  When installing geo.php, remember to set the $wgMapsourcesURL
 *  appropriately in LocalSettings.php
 *
 *  ----------------------------------------------------------------------
 *
 *  Copyright 2005, Egil Kvaleberg <egil@kvaleberg.no>
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

if ( !defined( 'MEDIAWIKI' ) ) {
        echo "GIS extension\n";
        exit( 1 );
}

require_once('mapsources.php');

class SpecialGeo extends SpecialPage {

	function __construct() {
		parent::__construct( 'Geo', 'geo' );
	}

	function execute( $subpage ) {
		global $wgOut, $wgRequest, $wgCookiePrefix;
		$params = $wgRequest->getValues();

		$wgOut->addHTML( '<form>');
		$wgOut->addHTML( '<select name="dist">' );
        $distances = array("0.3" => "метров", 
                           "0.5" => "метров",
                           "1" => "километр",
                           "1.5" => "километра");
		if ( isset( $params['dist'] ) && (! array_key_exists($params['dist'], $distances))) {
            if ($params['dist'] < 1) {
                $wgOut->addHTML( sprintf("<option selected value=\"{$params['dist']}\">%d метров</option>", ($params['dist'] * 1000)));
            } else {
                $wgOut->addHTML( "<option selected value=\"{$params['dist']}\">{$params['dist']} километров</option>" );
            }
            
		}
		foreach ( $distances as $d => $dname) {
		    $selected = ($d == $params['dist']) ? "selected" : "";
		    if ($d < 1) {
		        $wgOut->addHTML( sprintf("<option $selected value=\"{$d}\">%d $dname</option>", ($d * 1000)));
		    } else {
		      	$wgOut->addHTML( "<option $selected value=\"{$d}\">{$d} $dname</option>" );
            }
		}
		$wgOut->addHTML( '</select>' );
		unset( $params['dist'] );
		unset( $params['subaction'] );
		unset( $params[$wgCookiePrefix.'_session'] );

		foreach ( $params as $key => $val ) {
			$wgOut->addHTML( "<input type=\"hidden\" name=\"$key\" value=\"$val\"></input>\n" );
		}
        $wgOut->addHTML("<input type=\"hidden\" name=\"subaction\" value=\"near\"></input>");
		$wgOut->addHTML( "<input type=\"submit\" /></form>\n" );

		if ( $wgRequest->getVal( 'subaction' ) == 'near' ) {
			require_once('neighbors.php');
			$dist = $wgRequest->getVal( 'dist', 1000 );
			$bsl = new neighbors( $dist );
			$bsl->show();
		} elseif ( $wgRequest->getVal( 'subaction' ) == 'maparea' ) {
		    error_log("using unsupported subaction=\"maparea\", this parameter must always be \"near\"");
		} else {
			$bsl = new map_sources();
			$bsl->show();
		}
	}
}
