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
     * \param $nm name of neighbour as string
     * \param $d distance to neighbour in meters
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
    
    /** render array to category tree
     * \param $cattree Array() with category name as key and category children as value, or string. 
     * If string then consider as titile - category element.
     * \param $distances Array() with title as key and distnace in meters as value.
     * \param $prepostfix string appended and prepened to category name when rendering
     * \return rendered string to insert into page as mediawiki text 
    */   
    function renderCategory($cattree, $distances, $prepostfix = "===") {
        if (is_string($cattree)) {
            return $this->formatNeighbour($cattree, $distances[$cattree]);
        } elseif (is_array($cattree)) {
            $out = array();
            foreach($cattree as $catname => $childs) {
                $chstrings = asort(array_filter($childs, function($a) {return is_string($a);}), SORT_STRING);
                $charrays = asort(array_filter($childs, function($a) {return is_array($a);}), SORT_STRING);
                $ch1 = array();
                foreach($chstrings as $chstr) {
                    array_push($ch1, $this->renderCategory($chstr, $distances, $prepostfix . "="));
                }
                $ch2 = array();
                foreach($charrays as $charr) {
                    array_push($ch2, $this->renderCategory($charr, $distances, $prepostfix . "="));
                }
                array_push($out, "$prepostfix $catname $prepostfix");
                array_push($out, implode("\n\n", $ch1));
                array_push($out, implode("\n\n", $ch2)); 
            }
            return implode("\n\n", $out);
        } else {
            error_log("can not renderCategory, first argument must be string or array");
            return "";
        }
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
                $all[$id] = $gc->distance;
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
        
        if (! empty($ignore)) {
            $out = "=== Объекты возле: $ignore ===\n";
        } else {
            $out = "";
        }

        # Group all articles by categories
        
        $categorized = array(); # key is a category name, value is a list of titles
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
                    array_merge_recursive($categorized, $title->getParentCategoryTree());
                }
            }
        }
        # remove duplicates from non categorized titles and sort 
        $nocategory = array_unique($nocategory);
        asort($nocategory, SORT_STRING);
        reset($nocategory);
        
        # Generate output
        $catree = Title::getParentCategoryTree($categorized);
        
        $nocatdivs = array();
        foreach($nocategory as $title) {
            $nm = $title->getEscapedText();
            $d = $all[$title->getArticleID()];
            array_push($nocatdivs, $this->formatNeighbour($nm, $d));
        }
        
        $out .= implode("\n\n", $nocatdivs);
        $out .= "\n\n";
        $out .= $this->renderCategory($categorized, $all, "===");
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
