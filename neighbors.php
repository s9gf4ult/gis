<?php
/** @file
 *
 *  Create a page which link to other articles in Wikipedia which are
 *  in the neighborhood
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
require_once( 'greatcircle.php' );

/**
 *  Base class
 */
class neighbors {
	var $p;
	var $d;
	var $title;
	var $attr;

	function __construct( $dist )
	{
		$this->p = new GeoParam();
		$this->d = $dist;
		if ($this->d <= 0) $this->d = 1000; /* default to 1000 km */
		$this->title = $this->p->title;
		$this->attr = $this->p->get_attr();
	}

	function show()
	{
		global $wgOut, $wgUser, $wgContLang;

		/* No reason for robots to follow these links */
		$wgOut->setRobotPolicy( 'noindex,nofollow' );

		$wgOut->setPagetitle( "Neighbors" );

		if (($e = $this->p->get_error()) != "") {
			throw new FatalError( htmlspecialchars( $e ) );
		}

		$wgOut->addWikiText( $this->make_output() );
	}

	function make_output()
	{
		$lat0 = $this->p->latdeg;
		$lon0 = $this->p->londeg;

		$g = new GisDatabase();
		$g->select_radius_m( $lat0, $lon0, $this->d * 1000,
				     // FIXME: Notice: Undefined index:  globe in extensions\gis\neighbors.php on line 79
				     // FIXME: Notice: Undefined index:  type in extensions\gis\neighbors.php on line 79
				     $this->attr['globe'], $this->attr['type'],
				     // FIXME: Notice: Undefined index:  arg:type in extensions\gis\neighbors.php on line 81
				     $this->attr['arg:type'] );
		$all = array();

		while ( ( $x = $g->fetch_position() ) ) {
			$id = $x->gis_page;
			$lat = ($x->gis_latitude_min+$x->gis_latitude_max)/2;
			$lon = ($x->gis_longitude_min+$x->gis_longitude_max)/2;
			$gc = new greatcircle($lat,$lon, $lat0, $lon0);
			# FIXME: multiple geos in same page are overwritten
			if ($gc->distance > $this->d * 1000) {
				# ignore those points that are within the
				# bounding rectangle, but not within the radius
			} else {
			    array_push($all, $id);
                # just collect the id's of the articles
			}
		}
        
        # Group all articles by categories
        
        $categories = array(); # key is a category id, value is a list of titles
        $nocategory = array(); # titles witout category
        foreach($all as $id) {
            $title = Title::newFromID($id);
            if (is_null($title)) {
                error_log("Error loading article with id $id");
            }  else {
                $ctgs = $title->getParentCategories(); # get list of categories to which our title belongs
                foreach($ctgs as $ca => $ignore) {
                    $categ = Category::newFromName($ca);
                    $catid = $categ->getID();
                    if (!$catid) {
                        array_push($nocategory, "[[$title]]");
                    } else {
                        if (array_key_exists($catid, $categories)) { # push title to category if there is some
                            array_push($categories[$catid], $title);
                        } else {
                            $categories[$catid] = array($title);
                        }
                    }
                } 
            }
        }
        # remove duplicates from non categorized titles and sort 
        $nocategory = array_unique($nocategory);
        asort($nocategory, SORT_STRING);
        reset($nocategory);
        
        # sort titles and remove duplicates from categories
        foreach($categories as $cat => $titles) {
            $categories[$cat] = array_unique($titles);
            asort($categories[$cat]);
        }
        
        # sort by categories 
        ksort($categories);
        
        # Generate output
        
        $catdivs = array(); # must be placed into <dl> tag
        foreach($categories as $catid => $titles) {
            $cat = Category::newFromID($catid);
            $cn = $cat->getName();
            $catname = "===$cn===";
            $titledivs = array();      # list of formated titiles
            foreach($titles as $title) {
                $nm = $title->getEscapedText();
                array_push($titledivs, "[[$nm]]");
            }
            asort($titledivs, SORT_STRING);
            reset($titledivs);
            $titlevals = implode("\n\n", $titledivs);
            if (empty($cn)) {
                array_push($catdivs, "$titlevals");
            } else {
                array_push($catdivs, "$catname\n\n$titlevals");
            } 
        }
        $out = implode("\n\n", $nocategory);
        $out .= implode("\n\n", $catdivs);
        return $out;
	}

	function show_location( $id, $d, $pos )
	{
		$id = $pos->gis_page;

		$out = "<tr><th>[[{$pos['name']}]]</th>";

		$type = $pos['type'];

		$out .= "<td>$type</td>";
		if ($d < 1000) {
			$out .= '<td>'.round($d).' m</td>';
		} elseif ($d < 10000) {
			$out .= "<td>".round($d/100)/10 ." km</td>";
		} else {
			$d = round($d/1000);
			$dx = "";
			if ($d >= 1000) {
				$m = floor($d/1000);
				$dx .= $m.",";
				$d -= $m*1000;
			}
			$out .= "<td>$dx$d km</td>";
		}
		return "$out<td>{$pos['octant']}</td><td>bearing "
		       . round($pos['heading']) . "° towards "
		       . $this->p->make_position($pos['lat'],$pos['lon'])
		       . "</td></tr>\r\n";
	}
}
