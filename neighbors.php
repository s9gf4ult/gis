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
    
    /** get neighbour name and distance to in meters.
     * \return string to insert in mediawiki
     */
    function formatNeighbour($nm, $d) {
        if ($d >= 1000) {
            $dist = round($d / 1000, 1);
            $dist .= " километров";
        } else {
            $dist = round($d) . " метров";
        }
        
        $kmch = 5 * 1000 / 60; # 5km/h -> m/min
        $atime = round($d / $kmch) . " минут";                    
        return "* [[$nm]] $dist, примерно $atime пешком";
    }
	
	function formatMapLink($lat, $lon, $plat, $plon, $name = "карта") {
		global $wgServer;
		return "[[$wgServer/Special:YaMap?action=path&lat=$lat&lon=$lon&plat=$plat&plon=$plon | $name]]";
	}
    
    /** sort $names by distances
     * \param $names - array of Title instances
     * \param $dists - array of ArticleID => Distance
     * \return sorted array of Titile
     */
    function sortByDistance($names, $dists) {
        $a2title = Array(); # ArticleId => Title 
        foreach($names as $t) {
            $x = $t->getArticleID();
            $a2title[$x] = $t;
        }
        
        $c = array_intersect_key($dists, $a2title); # ArticleID => Distance
        asort($c, SORT_NUMERIC); # ArticleID => Distance sorted by distance
        $akeys = array_keys($c);  # sorted articleID
        $ret = Array();
        foreach($akeys as $ak) {
            array_push($ret, $a2title[$ak]);
        }
		
        return $ret; # Array($names[0]);
    }

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
        
        global $wgRequest;
        $ignore = $wgRequest->getVal("ignore");
        if (empty($ignore)) {
            $wgOut->setPagetitle( "Рядом находится" );
        } else {
            $wgOut->setPagetitle("$ignore: рядом расположены");
        }
            
        

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
		$all = array(); # key is article id, value is distance
		$latitude = array();
		$longitude = array();
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
                $all[$id] = $gc->distance;
				$latitude[$id] = $lat;
				$longitude[$id] = $lon;
                # just collect the id's of the articles
			}
		}

        # Delete ignoring articles            
            
        global $wgRequest;
        $ignore = $wgRequest->getVal("ignore");
        if (! empty($ignore)) {
            foreach($all as $articleid => $d) {
                $title = Title::newFromID($articleid);
                if (empty($title)) {
                    error_log("Can not create title from id $articleid");
                } else {
                    if ($title->getBaseText() == $ignore) {
                        unset($all[$articleid]);
                    }
                }
            }
        }
        
        if (count($all) == 0) {
            return "=== Поблизости нет объектов ===";
        }
        
       $out = "";

        # Group all articles by categories
        
        $categories = array(); # key is a category name, value is a list of titles
        $nocategory = array(); # titles witout category
        foreach($all as $id => $d) {
            $title = Title::newFromID($id);
            if (is_null($title)) {
                error_log("Error loading article with id $id");
            }  else {
                $ctgs = $title->getParentCategories(); # get list of categories to which our title belongs
                if (empty($ctgs)) {
                    array_push($nocategory, $title);
                } else {
                    foreach($ctgs as $ca => $ignore) {
                        $ca = trim($ca);
                        if (array_key_exists($ca, $categories)) { # push title to category if there is some
                            array_push($categories[$ca], $title);
                        } else {
                            $categories[$ca] = array($title);
                        }
                    }
                } 
            }
        }
        # remove duplicates from non categorized titles and sort 
        $nocategory = array_unique($nocategory);
        asort($nocategory, SORT_STRING);
        reset($nocategory);
		
		# remove ignoring categories
        global $wgGisNeighbourIgnoreCat;
        foreach($wgGisNeighbourIgnoreCat as $v) {
            unset($categories[$v]);
        }
        
        # sort titles and remove duplicates from categories
        foreach($categories as $cat => &$titles) {
            $x = array_unique($titles);
            $titles = $this->sortByDistance($x, $all);
        }
        unset($titles);
        
        # Generate output
        
       
        
        $catdivs = array(); # must be placed into <dl> tag
        foreach($categories as $catname => $titles) {
            $cat = Category::newFromName($catname);
            $cn = $cat->getTitle()->getText();
            $cn = str_replace(":", ": ", $cn);
            $cn = "===$cn===";
            $titledivs = array();      # list of formated titiles
            foreach($titles as $title) {
                $nm = $title->getEscapedText();
				$id = $title->getArticleID();
                $d = $all[$id];
                array_push($titledivs, $this->formatNeighbour($nm, $d) . " " .
                $this->formatMapLink($lat0, $lon0, $latitude[$id], $longitude[$id]));
            }
            $titlevals = implode("\n", $titledivs);
            if (!$cat) {
                array_push($catdivs, "$titlevals");
            } else {
                array_push($catdivs, "$cn\n\n$titlevals");
            } 
        }
        
        $nocatdivs = array();
        foreach($nocategory as $title) {
            $nm = $title->getEscapedText();
            $d = $all[$title->getArticleID()];
            array_push($nocatdivs, $this->formatNeighbour($nm, $d));
        }
        
        $out .= implode("\n\n", $nocatdivs);
        $out .= "\n\n";
        $out .= implode("\n\n", $catdivs);
        $out .= "\n";
        $out .= "__NOTOC__\n";
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
