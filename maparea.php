<?php
/** @file
 *
 *  List all locations within an area in a format compatible with Wikimaps
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


include_once ( "gissettings.php" ) ;

require_once( "geo.php" );
require_once( "database.php" );

/**
 *  Base class
 */
class maparea {
	var $p;
	var $attr;

	function maparea( $coor )
	{
		$this->p = new geo_param( $coor );
		$this->attr = $this->p->get_attr();
	}

	function show() 
	{
		global $wgOut, $wgUser, $wgContLang;

		/* No reason for robots to follow map links */
		$wgOut->setRobotpolicy( 'noindex,nofollow' );

		$wgOut->setPagetitle( "Maparea" );

		if (($e = $this->p->get_error()) != "") {
			$wgOut->addHTML(
			       "<p>" . htmlspecialchars( $e ) . "</p>");
			$wgOut->output();
			wfErrorExit();
			return;
		}

		$wgOut->addWikiText( $this->make_output() );
	}

	function make_output()
	{
		$g = new gis_database();
		$g->select_area( $this->p->latdeg_min, $this->p->londeg_min,
				 $this->p->latdeg_max, $this->p->londeg_max,
				 $this->attr['region'], $this->attr['type'] );

		$out = ";type:abstract\r\n"
		     . ";comment:"
		     .  $this->map_pos($this->p->latdeg_min,$this->p->londeg_min) . " to "
		     .  $this->map_pos($this->p->latdeg_max,$this->p->londeg_max) . " ("
		     .  $this->p->get_markup() . ")\r\n"
		     . ";region:include(#*)\r\n"
		     . "\r\n";

		while (($x = $g->fetch_position())) {
			$id = $x->gis_id;
			$lat = ($x->gis_latitude_min+$x->gis_latitude_max)/2;
			$lon = ($x->gis_longitude_min+$x->gis_longitude_max)/2;
			$type = $x->gis_type;
			if ($type == "") $type = "unknown";
			$out .= "==[[" . $g->get_title($id) . "]]==\r\n"
			      . ";type:" . $type . "\r\n"
			      . ";data:" . $this->map_pos($lat,$lon) . "\r\n"
			      . "\r\n";
		}
		return $out;
	}
	
	function map_pos( $lat, $lon )
	{
		#
		#  NOTE: This is the existing wikmap format
		#  Simplify when wikmaps understand decimal degrees
		#
		$a = geo_param::make_minsec( $lat );
		$b = geo_param::make_minsec( $lon );
		$out = "";
		if ($lat < 0) $out .= "-";
		if (intval(abs( $a['deg'])) <= 9) $out .= "0";
		$out .= intval(abs($a['deg']));
		if (intval($a['min']) <= 9) $out .= "0";
		$out .= intval($a['min']);
		if (intval($a['sec']) <= 9) $out .= "0";
		$out .= intval($a['sec']) . ",";
		if ($lon < 0) $out .= "-";
		if (intval(abs( $b['deg'])) <= 9) $out .= "0";
		$out .= intval(abs($b['deg']));
		if (intval($b['min']) <= 9) $out .= "0";
		$out .= intval($b['min']);
		if (intval($b['sec']) <= 9) $out .= "0";
		$out .= intval($b['sec']);
		return $out;
	}
}
?>
