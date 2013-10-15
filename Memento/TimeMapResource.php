<?php
/**
 * This file is part of the Memento Extension to MediaWiki
 * http://www.mediawiki.org/wiki/Extension:Memento
 *
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 * 
 * @file
 */

/**
 * This class provides the base functions for all Memento TimeMap types
 */
abstract class TimeMapResource extends MementoResource {

	/*
		note that these patterns are just for detecting timestamp
		pivots; error checking on the length and value of the timestamp
		happens within the class generated by the timeMapFactory
 	*
 	* TODO: move these member variables into TimeMapResource.
 	*
	*/
	const ascendingURLPattern = "^[0-9]+\/1\/";
	const descendingURLPattern = "^[0-9]+\/-1\/";

	/*
		this pattern exists to detect that a timestamp pivot exists, not
		which type
	*/
	const pivotURLPattern = "^[0-9]+\/-*[0-9]\/";

	/**
	 * containsPivot
	 *
	 * Return true if the URL matches the pivot pattern.
	 *
	 * TODO: move this function into TimeMapResource as a static function.
	 *
	 */
	public static function containsPivot($urlparam) {
		return (
			preg_match( '/' .  TimeMapResource::pivotURLPattern .
				'/', $urlparam ) == 1 );
	}

	/**
	 * isPivotAscending
	 *
	 * Return true if the URL is for a TimeMap ascending from a pivot.
	 *
	 * TODO: move this function into TimeMapResource as a static function.
	 *
	 */
	public static function isPivotAscending($urlparam) {
		return (
			preg_match( '/' . TimeMapResource::ascendingURLPattern . 
				'/', $urlparam ) == 1 );
	}

	/**
	 * isPivotDescending
	 *
	 * Return true if the URL is for a TimeMap descending from a pivot.
	 *
	 * TODO: move this function into TimeMapResource as a static function.
	 *
	 */
	public static function isPivotDescending($urlparam) {
		return (
			preg_match( '/' . TimeMapResource::descendingURLPattern .
				'/', $urlparam ) == 1 );
	}

	/**
	 * timeMapFactory
	 *
	 * This function determines which TimeMap object behavior we will get
	 * based on the input.
	 *
	 * TODO: move this function into TimeMapResource as a static function.
	 *
	 */
	public static function timeMapFactory(
		$config, $dbr, $article, $urlparam ) {

		if ( TimeMapResource::containsPivot( $urlparam ) ) {
			if ( TimeMapResource::isPivotAscending( $urlparam ) ) {
				$tm = new TimeMapPivotAscendingResource(
					$config, $dbr, $article );
			} elseif ( TimeMapResource::isPivotDescending( $urlparam ) ) {
				$tm = new TimeMapPivotDescendingResource(
					$config, $dbr, $article );
			} else {
				$titleMessage = 'timemap';
				$textMessage = 'timemap-400-date';
				$out = $article->getContext()->getOutput();
				$response = $out->getRequest()->response();

				throw new MementoResourceException(
					$textMessage, $titleMessage,
					$out, $response, 400, array( '' )
				);
			}
		} else {
			$tm = new TimeMapFullResource( $config, $dbr, $article );
		}

		return $tm;
	}

	/**
	 * getTitle
	 *
	 * This function extracts the Title from the URL
	 */

	public static function deriveTitleObject( $urlparam ) {

		if ( TimeMapResource::isPivotAscending( $urlparam ) ) {
			$title = preg_replace(
				'/' . TimeMapResource::ascendingURLPattern . '/',
				"", $urlparam );
			$title = Title::newFromText( $title );
		} elseif ( TimeMapResource::isPivotDescending( $urlparam ) ) {
			$title = preg_replace(
				'/' . TimeMapResource::descendingURLPattern . '/',
				"", $urlparam );
			$title = Title::newFromText( $title );
		} else {
			$title = Title::newFromText( $urlparam );
		}

		return $title;
	}

	/**
	 * getDescendingTimeMapData
	 *
	 * Extract the full time map data from the database.
	 *
	 * @param $pg_id - identifier of the requested page
	 * @param $timestamp - the timestamp to query for
	 *
	 * @return $data - array with keys 'rev_id' and 'rev_timestamp' containing
	 *		the revision ID and the revision timestamp respectively
	 */
	public function getDescendingTimeMapData($pg_id, $timestamp) {

		$limit = $this->conf->get('NumberOfMementos');

		$data = array();

		$results = $this->dbr->select(
			'revision',
			array( 'rev_id', 'rev_timestamp'),
			array(
				'rev_page' => $pg_id,
				'rev_timestamp<' . $this->dbr->addQuotes( $timestamp )
				),
			__METHOD__,
			array(
				'ORDER BY' => 'rev_timestamp DESC',
				'LIMIT' => $limit
				)
			);

		while($result = $results->fetchRow()) {
			$datum = array();
			$datum['rev_id'] = $result['rev_id'];
			$datum['rev_timestamp'] = wfTimestamp(
				TS_RFC2822, $result['rev_timestamp']
				);
			$data[] = $datum;
		}

		return $data;
	}

	/**
	 * getAscendingTimeMapData
	 *
	 * Extract the full time map data from the database.
	 *
	 * @param $pg_id - identifier of the requested page
	 * @param $timestamp - the timestamp to query for
	 *
	 * @return $data - array with keys 'rev_id' and 'rev_timestamp' containing
	 *		the revision ID and the revision timestamp respectively
	 */
	public function getAscendingTimeMapData($pg_id, $timestamp) {

		$limit = $this->conf->get('NumberOfMementos');

		$data = array();

		$results = $this->dbr->select(
			'revision',
			array( 'rev_id', 'rev_timestamp'),
			array(
				'rev_page' => $pg_id,
				'rev_timestamp>' . $this->dbr->addQuotes( $timestamp )
				),
			__METHOD__,
			array(
				'ORDER BY' => 'rev_timestamp ASC',
				'LIMIT' => $limit
				)
			);

		/*
		 I couldn't figure out how to make the select function do 
		 the following:
		 SELECT rev_id, rev_timestamp FROM (SELECT  rev_id,rev_timestamp
		 FROM `revision`  WHERE rev_page = '2' AND
		 (rev_timestamp>'20120101010100')  ORDER BY rev_timestamp
		 ASC LIMIT 3 ) as tempsorter ORDER BY rev_timestamp DESC;
		 so the following code performs the sort in PHP
		*/

		$interim = array();

		while ($result = $results->fetchRow()) {
			$interim[$result['rev_timestamp']] = $result['rev_id'];	
		}

		if ( krsort($interim) )  {

			foreach ($interim as $timestamp => $id ) {
				$datum = array();
				$datum['rev_id'] = $id; 
				$datum['rev_timestamp'] = wfTimestamp(
					TS_RFC2822, $timestamp
					);
				$data[] = $datum;
			}

		}

		return $data;
	}

	/**
	 * generateAscendingTimeMapPaginationData
	 *
	 * @param $pg_id - the ID of the page, not the oldid
	 * @param $pivotTimestamp - the pivotTimestamp in TS_MW format
	 * @param $timeMapPages - array passed by reference to hold TimeMap pages
	 * @param $title - the title of the page
	 *
	 * @return $timeMapPages - same array that was passed by reference
	 *			and altered, but now contains an entry that is an array with
	 *			keys of uri, from, and until representing the next Time Map,
	 *			its starting time and ending time
	 *
	 */
	 public function generateAscendingTimeMapPaginationData(
	 	$pg_id, $pivotTimestamp, &$timeMapPages, $title ) {

		$paginatedResults = $this->getAscendingTimeMapData(
			$pg_id, $pivotTimestamp
			);
		
		$timeMapPage = array();

		$timeMapPage['until'] = $paginatedResults[0]['rev_timestamp'];
		$earliestItem = end($paginatedResults);
		reset($paginatedResults);
		$timeMapPage['from'] = wfTimestamp( TS_RFC2822, $pivotTimestamp );	
		
		$timeMapPage['uri'] = $this->mwbaseurl . '/' 
			. SpecialPage::getTitleFor('TimeMap') . '/'
			. $pivotTimestamp . '/1/' . $title;
		
		array_push( $timeMapPages, $timeMapPage );

		return $timeMapPages;
	}

	/**
	 * generateDescendingTimeMapPaginationData
	 *
	 * @param $pg_id - the ID of the page, not the oldid
	 * @param $pivotTimestamp - the pivotTimestamp in TS_MW format
	 * @param $timeMapPages - array passed by reference to hold TimeMap pages
	 * @param $title - the title of the page
	 *
	 * @return $timeMapPages - same array that was passed by reference
	 *			and altered, but now contains an entry that is an array with
	 *			keys of uri, from, and until representing the next Time Map,
	 *			its starting time and ending time
	 *
	 */
	 public function generateDescendingTimeMapPaginationData(
	 	$pg_id, $pivotTimestamp, &$timeMapPages, $title ) {

		$paginatedResults = $this->getDescendingTimeMapData(
			$pg_id, $pivotTimestamp
			);
		
		$timeMapPage = array();
		
		$timeMapPage['until'] = $paginatedResults[0]['rev_timestamp'];
		$earliestItem = end($paginatedResults);
		reset($paginatedResults);
		$timeMapPage['from'] = wfTimestamp( TS_RFC2822, $pivotTimestamp );	
		
		$timeMapPage['uri'] = $this->mwbaseurl . '/' 
			. SpecialPage::getTitleFor('TimeMap') . '/'
			. $pivotTimestamp . '/-1/' . $title;
		
		array_push( $timeMapPages, $timeMapPage );

		return $timeMapPages;
	}

	/**
	 * extractTimestampPivot
	 *
	 * @param $urlparam - the parameter passed to execute() in this SpecialPage
	 *
	 * @returns timestamp, if found; null otherwise
	 */
	public function extractTimestampPivot( $urlparam ) {
		$pivot = null;

		$prefix = $this->mwrelurl . '/' .
			SpecialPage::getTitleFor('TimeMap') . '/';

		$urlparam = str_replace($prefix, '', $urlparam);

		$pattern = "/^([0-9]{14})\/.*/";

		preg_match($pattern, $urlparam, $matches);

		if ( count($matches) == 2 ) {
			$pivot = $matches[1];
		} else {
			$pivot = null;
		}

		return $pivot;
	}

	/**
	 * formatTimestamp
	 *
	 * Wrapper for wfTimestamp that catches exceptions so the caller can issue 
	 * its own error statements instead.
	 *
	 * @see http://www.mediawiki.org/wiki/Manual:WfTimestamp
	 *
	 * @param $timestamp
	 *
	 * @returns formatted timestamp; null if error
	 */
	public function formatTimestampForDatabase( $timestamp ) {

		$formattedTimestamp = null;

		try {
			$formattedTimestamp = wfTimestamp( TS_MW, $timestamp );

			if ( $formattedTimestamp === false ) {
				// the timestamp is unrecognized, but not incorrectly formatted?
				$formattedTimestamp = null;
			}

		} catch ( MWException $e ) {
			// it all went wrong, we passed in bad data
			$formattedTimestamp = null;
		}

		return $formattedTimestamp;
	}

	/**
	 * generateTimeMapText
	 *
	 * Generates Time Map text as per examples in Memento TimeMap RFC
	 * @see http://www.mementoweb.org/guide/rfc/ID/
	 *
	 * @param $data - array with entries containing the keys
	 *					rev_id and rev_timestamp
	 * @param $timeMapURI - used to construct self TimeMap URI relation
	 * @param $title - the page name that the TimeMap is for
	 * @param $pagedTimeMapEntries - array of arrays, each entry containing
	 *			the keys 'uri', 'from', and 'until' referring to the URI of
	 *			the TimeMap and its from and until dates
	 *
	 * @returns formatted timemap as a string
	 */
	public function generateTimeMapText(
		$data, $timeMapURI, $title, $pagedTimeMapEntries = array() ) {

		$outputArray = array();

		$timegateuri = $this->getSafelyFormedURI( $title );
		$timegateEntry = $this->constructLinkRelationHeader(
			$timegateuri, 'original latest-version timegate' );

		$from = $data[count($data) - 1]['rev_timestamp'];
		$until = $data[0]['rev_timestamp'];

		$timemapEntry = '<' . $timeMapURI . 
			'>; rel="self"; type="application/link-format"; ' .
			'from="' . $from . '; until="' . $until . '"';

		array_push( $outputArray, $timemapEntry );

		foreach ( $pagedTimeMapEntries as &$pagedTimeMap ) {

			# TODO: make this a function
			$pagedTimemapEntry = '<' . $pagedTimeMap['uri'] .
				'>; rel="timemap"; type="application/link-format";' .
				'from="' . $pagedTimeMap['from'] . '"; ' .
				'until="' . $pagedTimeMap['until'] . '"';
				
			array_push( $outputArray, $pagedTimemapEntry );	
		}

		array_push( $outputArray, $timegateEntry );

		for ($i = count($data) - 1; $i >= 0; $i--) {
			$output = "";
			$datum = $data[$i];

			$output = $this->constructMementoLinkHeaderEntry(
				$title, $datum['rev_id'], $datum['rev_timestamp'], "memento" );

			array_push($outputArray, $output);
		}

		// the original implementation of TimeMap for Mediawiki used ,<SP><LF>
		// to separate the entries and added a \n at the end
		$timemap = implode(",\n", $outputArray);

		return $timemap;
	}

	/**
	 * renderPivotTimeMap
	 *
	 * This template method handles all of the operations of rendering a
	 * PivotAscending or PivotDescending TimeMap.  It requires the
	 * implementation of the abstract class getPivotTimeMapData.  It is
	 * meant to be called form alterEntity.
	 *
	 */
	public function renderPivotTimeMap() {
		$article = $this->article;
		$out = $article->getContext()->getOutput();
		$titleObj = $article->getTitle();

		$server = $this->conf->get('Server');
		$pg_id = $titleObj->getArticleID();
		$request = $out->getRequest();
		$response = $request->response();

		$requestURL = $request->getRequestURL();
		$timeMapURI = $request->getFullRequestURL();

		if ( $pg_id > 0 ) {

			$timestamp = $this->extractTimestampPivot( $requestURL );

			if (!$timestamp) {
				// we can't trust what came back, and we don't know the pivot
				// so the parameter array is empty below
				throw new MementoResourceException(
					'timemap-400-date', 'timemap',
					$out, $response, 400,
					array( '' ) );
			}

			$formattedTimestamp =
				$this->formatTimestampForDatabase( $timestamp );

			$results = $this->getPivotTimeMapData(
				$pg_id, $formattedTimestamp
				);

			// this section is rather redundant when we throw 400 for
			// the timestamp above, but exists in case some how an invalid
			// timestamp is extracted
			if (!$results) {
				throw new MementoResourceException(
					'timemap-400-date', 'timemap',
					$out, $response, 400,
					array( $timestamp )
				);
			}

			$latestItem = $results[0];
			$earliestItem = end($results);
			reset($results);

			$firstId = $titleObj->getFirstRevision()->getId();
			$lastId = $titleObj->getLatestRevId();

			# this counts revisions BETWEEN, non-inclusive
			$revCount = $titleObj->countRevisionsBetween(
				$firstId, $earliestItem['rev_id'] );
			$revCount = $revCount + 2; # for first and last

			$timeMapPages = array();

			$title = $titleObj->getPrefixedURL();

			# if $revCount is higher, then we've gone over the limit
			if ( $revCount > $this->conf->get('NumberOfMementos') ) {

				$pivotTimestamp = $this->formatTimestampForDatabase(
					$earliestItem['rev_timestamp'] );

				$this->generateDescendingTimeMapPaginationData(
					$pg_id, $pivotTimestamp, $timeMapPages, $title );

			}

			# this counts revisions BETWEEN, non-inclusive
			$revCount = $titleObj->countRevisionsBetween(
				$latestItem['rev_id'], $lastId );
			$revCount = $revCount + 2; # for first and last

			# if $revCount is higher, then we've gone over the limit
			if ( $revCount > $this->conf->get('NumberOfMementos') ) {

				$pivotTimestamp = $this->formatTimestampForDatabase(
					$latestItem['rev_timestamp'] );

				$this->generateAscendingTimeMapPaginationData(
					$pg_id, $pivotTimestamp, $timeMapPages, $title );

			}

			echo $this->generateTimeMapText(
				$results, $timeMapURI, $title, $timeMapPages
				);

			$response->header("Content-Type: application/link-format", true);

			$out->disable();
		} else {
			$titleMessage = 'timemap';
			$textMessage = 'timemap-404-title';
			$title = $this->getFullNamespacePageTitle( $titleObj );

			throw new MementoResourceException(
				$textMessage, $titleMessage,
				$out, $response, 404, array( $title )
			);
		}
	}

	/**
	 * getPivotTimeMapData
	 *
	 * Method that acquires TimeMap data, based on a given formatted timestamp.
	 *
	 * @param $page_id
	 * @param $formattedTimestamp
	 *
	 * @return $data - array with keys 'rev_id' and 'rev_timestamp' containing
	 *		the revision ID and the revision timestamp respectively
	 */
	abstract public function getPivotTimeMapData(
		$page_id, $formattedTimestamp );
}
