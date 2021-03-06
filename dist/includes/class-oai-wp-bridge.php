<?php
/**
 * OAI WP Bridge
 *
 * @link       https://www.kennisnet.nl
 * @since      1.0.0
 *
 * @package    wpoaipmh
 */

use Picturae\OaiPmh\Exception\BadArgumentException;

class wpoaipmh_OAI_WP_bridge extends wpoaipmh_WP_bridge
{
	
	protected static $set_prefix = 'leraar24';
	
	protected static $edustandaard_sectorids = array (
			'VO'	=> '2a1401e9-c223-493b-9b86-78f6993b1a8d',
			'PO'	=> '512e4729-03a4-43a2-95ba-758071d1b725',
			'SO'	=> 'e7f1c08f-08fb-48ab-be8e-c131b1bce54a',
			'BVE'	=> 'f3ac3fbb-5eae-49e0-8494-0a44855fff25',
	);
	
	/**
	 * Queries DB for first published date
	 * 
	 * @return DateTime
	 */
	public function get_earliest_date( ) {
		global $wpdb;
		$date = $wpdb->get_var ( 'SELECT MIN(modified_date) FROM '.self::get_table('oai') );
		return $this->helper_convertdate( $date );	
	}
	
	/**
	 * Returns published date OR (if present) the entered modified date by author
	 * 
	 * @param unknown $record
	 * @return unknown
	 */
	protected function get_publisher_published_or_modified_date ( $record ) {
		$published_date = $record->modified_date;
		if( $record->modified_date_entered ) {
			$published_date = $record->modified_date_entered;
		}
		return $published_date;
	}
	
	/**
	 * Create meta subset
	 * 
	 * @param unknown $record
	 * @param unknown $no_meta
	 * @param unknown $metadataFormat
	 * @return string
	 */
	protected function get_meta( $record, $no_meta, $metadataFormat = 'lom' ) {
		
		/**
		 * Just the header (which is empty)
		 */
		if ( $no_meta ) {			
			$this->record_meta = $this->helper_meta_create_structure( 'root');
			$newelem = $this->helper_meta_create_structure( 'node');
			$this->record_meta = $this->helper_meta_add_sub($this->record_meta, $newelem);
			
			return $this->helper_meta_to_string( $this->record_meta );
		}
		
		if( $metadataFormat != 'lom' ) {
			throw new BadArgumentException( 'MetadataFormat not allowed' );
		}
		
		/**
		 * Only for full requests of records below
		 */
		$attribs = array( 
				array('key' => 'xmlns:lom', 'val' => 'http://www.imsglobal.org/xsd/imsmd_v1p2'),
		);
		$this->record_meta = $this->helper_meta_create_structure( 'lom:lom', array(), $attribs );
		
		$attribs_lang_nl = array(
				array('key' => 'xml:lang', 'val' => 'nl'),
		);
		$attribs_lang_none = array(
				array('key' => 'xml:lang', 'val' => 'x-none'),
		);
		$lom_version = 'LOMv1.0';
		$vcard_leraar24 = "BEGIN:VCARD\nVERSION:3.0\nN:Leraar24\nFN:Leraar24\nURL:https://www.leraar24.nl\nEND:VCARD";
		$rights_description_langstring_entry = 'Op Leraar24 gepubliceerde (artikel)teksten zijn, tenzij anders aangegeven, onder naamsvermelding vrij te gebruiken, ';
		$rights_description_langstring_entry .= 'te delen, en aan te passen, in lijn met de Creative Commons licentie CC BY-SA. Meer informatie is te vinden op ';
		$rights_description_langstring_entry .= 'https://www.leraar24.nl/disclaimer/.';

		/**
		 * General
		 */
		// Title
		$general_title_string = $this->helper_meta_create_structure( 'lom:langstring', array(), $attribs_lang_nl, htmlspecialchars( $record->title ) );
		$general_title = $this->helper_meta_create_structure( 'lom:title', array( $general_title_string ) );
		
		// Catalogentry
		$general_catalogentry_catalog = $this->helper_meta_create_structure( 'lom:catalog', array(), array(), 'URI');
		$general_catalogentry_entry_langstring = $this->helper_meta_create_structure( 'lom:langstring', array(), $attribs_lang_none, $record->permalink );
		$general_catalogentry_entry = $this->helper_meta_create_structure( 'lom:entry', array( $general_catalogentry_entry_langstring ) );
		$general_catalogentry = $this->helper_meta_create_structure( 'lom:catalogentry', array( $general_catalogentry_catalog, $general_catalogentry_entry ) );

		// Language
		$general_language = $this->helper_meta_create_structure( 'lom:language', array(), array(), 'nl');
		
		// AggregationLevel
		$general_aggregationLevel_source = $this->helper_meta_create_structure( 'lom:source', array(), array(), $lom_version );
		$general_aggregationLevel_value = $this->helper_meta_create_structure( 'lom:value', array(), array(), '2');
		$general_aggregationLevel = $this->helper_meta_create_structure( 'lom:aggregationlevel', array( $general_aggregationLevel_source, $general_aggregationLevel_value ));

		// Description
		$general_description_langstring = $this->helper_meta_create_structure( 'lom:langstring', array(), $attribs_lang_nl, htmlspecialchars( $record->post_excerpt ) );
		$general_description = $this->helper_meta_create_structure( 'lom:description', array( $general_description_langstring ), array());
		
		// Build list, add taxonomies later
		$general_subs = array(
				$general_title,
				$general_catalogentry,
				$general_language,
				$general_aggregationLevel,
				$general_description,
		);

		$sectors = self::get_linked_taxonomy_terms( $record->ID, $this->taxonomy_ids['sector'] );
		$post_competences = self::get_linked_taxonomy_terms( $record->ID, $this->taxonomy_ids['post_competence'] );
		$post_tags = self::get_linked_taxonomy_terms( $record->ID, $this->taxonomy_ids['post_tag'] );
		
		// Taxonomy: Bekwaamheids
		if(is_array($post_competences) && count($post_competences) > 0) {
			foreach( $post_competences as $tag ) {
				$keyword_sub = $this->helper_meta_create_structure( 'lom:langstring', array(), $attribs_lang_nl, $tag->term);
				$general_subs[] = $this->helper_meta_create_structure( 'lom:keyword', array( $keyword_sub ) );
			}
		}
		
		// Taxonomy: Tags
		if(is_array($post_tags) && count($post_tags) > 0) {
			foreach( $post_tags as $tag ) {				
				$keyword_sub = $this->helper_meta_create_structure( 'lom:langstring', array(), $attribs_lang_nl, $tag->term);
				$general_subs[] = $this->helper_meta_create_structure( 'lom:keyword', array( $keyword_sub ) );
			}
		}
		
		
		$newelem_general = $this->helper_meta_create_structure( 'lom:general', $general_subs );
		$this->record_meta = $this->helper_meta_add_sub($this->record_meta, $newelem_general);
		
		
		/**
		 * Lifecycle
		 */
		$lifecyle_contributes = array();
		
		$contribute_role_source = $this->helper_meta_create_structure( 'lom:source', array(), $attribs_lang_none, 'http://download.edustandaard.nl/vdex/vdex_lifecycle_contribute_role_lomv1p0_20060628.xml' );
		$contribute_role_value = $this->helper_meta_create_structure( 'lom:value', array(), $attribs_lang_none, 'publisher' );
		$contribute_role = $this->helper_meta_create_structure( 'lom:role', array( $contribute_role_source, $contribute_role_value ) );

		
		$contribute_centity_vcard = $this->helper_meta_create_structure( 'lom:vcard', array(), array(), $vcard_leraar24);
		$contribute_centity = $this->helper_meta_create_structure( 'lom:centity', array( $contribute_centity_vcard ));
		$contribute_date_datetime = $this->helper_meta_create_structure( 'lom:datetime', array(), array(), substr( $this->get_publisher_published_or_modified_date( $record ), 0, 10) );
		$contribute_date = $this->helper_meta_create_structure( 'lom:date', array( $contribute_date_datetime ));
		$lifecyle_contributes[] = $this->helper_meta_create_structure( 'lom:contribute', array( $contribute_role, $contribute_centity, $contribute_date ) );
		
		$contribute_role_source = $this->helper_meta_create_structure( 'lom:source', array(), $attribs_lang_none, 'http://download.edustandaard.nl/vdex/vdex_lifecycle_contribute_role_lomv1p0_20060628.xml' );
		$contribute_role_value = $this->helper_meta_create_structure( 'lom:value', array(), $attribs_lang_none, 'author' );
		$contribute_role = $this->helper_meta_create_structure( 'lom:role', array( $contribute_role_source, $contribute_role_value ) );
		$vcard = $vcard_leraar24;
		if( $record->partner_name ) {
			$vcard = "BEGIN:VCARD\nVERSION:3.0\nN:".htmlspecialchars( $record->partner_name )."\nFN:".htmlspecialchars( $record->partner_name )."\nEND:VCARD";
		}
		$contribute_centity_vcard = $this->helper_meta_create_structure( 'lom:vcard', array(), array(), $vcard);
		$contribute_centity = $this->helper_meta_create_structure( 'lom:centity', array( $contribute_centity_vcard ) );
		$lifecyle_contributes[] = $this->helper_meta_create_structure( 'lom:contribute', array( $contribute_role, $contribute_centity ) );
		
		
		$newelem_lifecycle = $this->helper_meta_create_structure( 'lom:lifecycle', $lifecyle_contributes );
		$this->record_meta = $this->helper_meta_add_sub($this->record_meta, $newelem_lifecycle);
		
		
		/**
		 * Technical
		 */
		// TODO: duration (?) (LER-146?)
		$technicals = array();
		$technical_format = $this->helper_meta_create_structure( 'lom:format', array(), array(), 'text/html' );
		$technicals[] = $technical_format;
		$technical_location = $this->helper_meta_create_structure( 'lom:location', array(), array(), $record->permalink );
		$technicals[] = $technical_location;
		
		
		$newelem_technical = $this->helper_meta_create_structure( 'lom:technical', $technicals );
		$this->record_meta = $this->helper_meta_add_sub($this->record_meta, $newelem_technical);
		
		
		/**
		 * Educational
		 */
		// Taxonomy: Sector
		$educational_subs = array();
		$classification_subs = array(); // For usage in classification
		if(is_array($sectors) && count($sectors) > 0) {
			foreach( $sectors as $tag ) {
				$source_elem = $this->helper_meta_create_structure( 'lom:source', array(), array(), 'http://purl.edustandaard.nl/vdex_context_czp_20060628.xml');
				$value_elem = $this->helper_meta_create_structure( 'lom:value', array(), array(), $tag->term);
				$educational_context = $this->helper_meta_create_structure( 'lom:context', array( $source_elem, $value_elem) );
				$educational_subs[] = $educational_context;
				
				$taxon_entry_tag_elem = $this->helper_meta_create_structure( 'lom:langstring', array( ), $attribs_lang_nl, $tag->term );
				$taxon_entry_tag = $this->helper_meta_create_structure( 'lom:entry', array( $taxon_entry_tag_elem ) );
				$taxon_id_tag = $this->helper_meta_create_structure( 'lom:id', array(), array(), self::$edustandaard_sectorids[$tag->term]);
				$taxon_elem = $this->helper_meta_create_structure( 'lom:taxon', array( $taxon_id_tag, $taxon_entry_tag));
				$classification_subs[] = $taxon_elem;
			}
		}

		$educational_intendedEndUserRole_source = $this->helper_meta_create_structure( 'lom:source', array(), array(), $lom_version );
		$educational_intendedEndUserRole_value = $this->helper_meta_create_structure( 'lom:value', array(), array(), 'teacher' );
		$educational_intendedEndUserRole = $this->helper_meta_create_structure( 'lom:intendedenduserrole', array( $educational_intendedEndUserRole_source, $educational_intendedEndUserRole_value) );
		$educational_subs[] = $educational_intendedEndUserRole;

		$educational_learningResourceType_source = $this->helper_meta_create_structure( 'lom:source', array(), array(), 'http://purl.edustandaard.nl/vdex_classification_purpose_czp_20060628.xml' );
		$educational_learningResourceType_value = $this->helper_meta_create_structure( 'lom:value', array(), array(), 'informatiebron' );
		$educational_learningResourceType = $this->helper_meta_create_structure( 'lom:learningresourcetype', array( $educational_learningResourceType_source, $educational_learningResourceType_value) );
		$educational_subs[] = $educational_learningResourceType;
		
		$newelem_educational = $this->helper_meta_create_structure( 'lom:educational', $educational_subs);
		$this->record_meta = $this->helper_meta_add_sub($this->record_meta, $newelem_educational);
		
		
		/**
		 * Rights
		 */
		$rights_subs = array();
		
		$rights_cost_source = $this->helper_meta_create_structure( 'lom:source', array(), array(), 'LOMv1.0' );
		$rights_cost_value = $this->helper_meta_create_structure( 'lom:value', array(), array(), 'no' );
		$rights_cost = $this->helper_meta_create_structure( 'lom:cost', array( $rights_cost_source, $rights_cost_value ) );
		$rights_subs[] = $rights_cost;
		
		$rights_copyrightandotherrestrictions_source_langstring = $this->helper_meta_create_structure( 'lom:langstring', array(), $attribs_lang_none, 'http://purl.edustandaard.nl/copyrightsandotherrestrictions_nllom_20131202' );
		$rights_copyrightandotherrestrictions_source = $this->helper_meta_create_structure( 'lom:source', array( $rights_copyrightandotherrestrictions_source_langstring ) );
		$rights_copyrightandotherrestrictions_value_langstring = $this->helper_meta_create_structure( 'lom:langstring', array(), $attribs_lang_none, 'yes' );
		$rights_copyrightandotherrestrictions_value = $this->helper_meta_create_structure( 'lom:value', array( $rights_copyrightandotherrestrictions_value_langstring ) );
		$rights_copyrightandotherrestrictions = $this->helper_meta_create_structure( 'lom:copyrightandotherrestrictions', array( $rights_copyrightandotherrestrictions_source, $rights_copyrightandotherrestrictions_value ) );
		$rights_subs[] = $rights_copyrightandotherrestrictions;
		
		$rights_description_langstring = $this->helper_meta_create_structure( 'lom:langstring', array(), $attribs_lang_nl, $rights_description_langstring_entry );
		$rights_description = $this->helper_meta_create_structure( 'lom:description', array( $rights_description_langstring) );
		$rights_subs[] = $rights_description;
		
		
		$newelem_rights = $this->helper_meta_create_structure( 'lom:rights', $rights_subs);
		$this->record_meta = $this->helper_meta_add_sub($this->record_meta, $newelem_rights);
		
		
		/**
		 * Classification
		 */
		$classification_purpose_source_langstring = $this->helper_meta_create_structure( 'lom:langstring', array(), $attribs_lang_none, 'http://purl.edustandaard.nl/vdex_classification_purpose_czp_20060628.xml' );
		$classification_purpose_source = $this->helper_meta_create_structure( 'lom:source', array( $classification_purpose_source_langstring ));
		$classification_purpose_value_langstring = $this->helper_meta_create_structure( 'lom:langstring', array(), $attribs_lang_none, 'educational level' );
		$classification_purpose_value = $this->helper_meta_create_structure( 'lom:value', array( $classification_purpose_value_langstring ));
		$classification_purpose = $this->helper_meta_create_structure( 'lom:purpose', array( $classification_purpose_source, $classification_purpose_value ) );
		
		
		$classification_taxonpath_langstring = $this->helper_meta_create_structure( 'lom:langstring', array( ), $attribs_lang_none, 'http://purl.edustandaard.nl/begrippenkader' );
		$classification_taxonpath_source = $this->helper_meta_create_structure( 'lom:source', array( $classification_taxonpath_langstring ) );
		$classification_taxonpath = $this->helper_meta_create_structure( 'lom:taxonpath', array_merge( array( $classification_taxonpath_source ), $classification_subs ) );
		
		$newelem_classification = $this->helper_meta_create_structure( 'lom:classification', array( $classification_purpose, $classification_taxonpath ) );
		$this->record_meta = $this->helper_meta_add_sub($this->record_meta, $newelem_classification);
		
		// Build metastring
		return $this->helper_meta_to_string( $this->record_meta );
	}
	
	/**
	 * Builds meta skeleton
	 * 
	 * @param string $name
	 * @param array $subs
	 * @param array $attribs
	 * @param string $content
	 * @return unknown[]|string[]
	 */
	protected function helper_meta_create_structure ( $name = '', $subs = array(), $attribs = array(), $content = '' ) {
		$rootstructure = array();
		
		// Sanity checks
		if( !is_array($subs) || isset($subs['name']) || !$name ) {
			echo 'Wrong call at line '.__LINE__;
			echo "\nArgs:\n";
			print_r(func_get_args());
			die();
		}

		$rootstructure['name']		= $name;
		$rootstructure['subs']		= $subs;
		$rootstructure['attribs']	= $attribs;
		$rootstructure['content']	= $content;
		
		return $rootstructure;
	}
	
	/**
	 * Adds sub item to array of subs
	 * 
	 * @param unknown $rootstructure
	 * @param unknown $sub
	 * @return unknown
	 */
	protected function helper_meta_add_sub ( $rootstructure, $sub ) {
		$rootstructure['subs'][] = $sub;
		return $rootstructure;
	}
	
	/**
	 * Converts rootstructure to string
	 * 
	 * @param unknown $rootstructure
	 * @return string
	 */
	protected function helper_meta_to_string ( $rootstructure, $level = 1 ) {
		$tabs = str_repeat("  ", $level);
		
		$currentstructure = '';
		// Build this root (name, attribs, content)
		$currentstructure .= '<'.$rootstructure['name'];
		
		// Set attribs
		foreach ( $rootstructure['attribs'] as $attrib ) {
			$currentstructure .= ' '.$attrib['key'];
			if( $attrib['val'] ) {
				$currentstructure .= '="'.$attrib['val'].'"';
			}
		}
		
		// Get children
		$children = '';
		foreach( $rootstructure['subs'] as $substructure ) {
			$children .= "\n".$this->helper_meta_to_string($substructure, ($level+1));
		}
		
		// No children and no content? Close current structure
		if( $children == '' && $rootstructure['content'] == '') {
			$currentstructure .= '/>';
		} else {
			// Either children OR content, close normally
			$currentstructure .= '>';
			if ( $rootstructure['content'] ) {
				// Show content
				$currentstructure .= $rootstructure['content'];
			} 
			if( $children ) {
				// Show children
				$currentstructure .= $children;
			}
			$currentstructure .= '</'.$rootstructure['name'].'>';
		}
		return $currentstructure;
	}
	
	/**
	 * Queries DB for records
	 * 
	 * @param DateTime $from
	 * @param DateTime $until
	 * @param unknown $set
	 * @param string $limit
	 * @param string $offset
	 * @param string $no_meta
	 * @param string $metadataFormat
	 * @param string $id (primary key)
	 * @return string[]|unknown[]|string[][][]|DateTime[][][]|NULL[][][]
	 */
	public function listRecords( $from = null, $until = null, $set = null, $limit = false, $offset = false, $no_meta = true, $metadataFormat = 'lom', $id = '') {
		global $wpdb;
		
		$sql_where = array();
		
		// Single record?
		if( $id ) {
			$single_query_ok = false;
			// [type]:[id]
			$tmp = explode(':', $id);
			$id_set = htmlspecialchars( $tmp[1] );
			$id_set = $this->post_type_convert_to_internal( $id_set );
			$id = intval( $tmp[2] );
			if( $id_set && $id ) {
				// Sanity check for post type
				if ( in_array($id_set, array_keys(self::$post_types) ) ) {
					$sql_where[] = $wpdb->prepare(' post_type = %s AND ID = %d', $id_set, $id );
					$single_query_ok = true;
				}
				
			}
			
			if( !$single_query_ok ) {
				$record_data  = array('status' => 'error', 'total' => 0, 'records' => false);
				return $record_data;
			}
		}
		
		// Only show publics and/or deleted (excluding private and future posts)
		$sql_where[] = ' is_ever_publicly_published = 1';
		if( $set ) {
			if ( in_array($this->post_type_convert_to_internal($set), array_keys(self::$post_types) ) ) {
				$sql_where[] = $wpdb->prepare(' post_type = %s', $this->post_type_convert_to_internal($set) );
			}
		}
		if( get_class($from) == 'DateTime' ) {
			$sql_where[] = $wpdb->prepare(' modified_date >= %s', $from->format('Y-m-d') );
		}
		if( get_class($until) == 'DateTime' ) {
			$sql_where[] = $wpdb->prepare(' modified_date < %s', $until->format('Y-m-d') );
		}
		
		$sql_where_full = implode(' AND ', $sql_where);
		if( is_array($sql_where) && count($sql_where) > 0 ) {
			$sql_where_full = 'WHERE  '.$sql_where_full. ' ';
		} else {
			$sql_where_full = '';
		}
		
		$sql_full = '';
		// Need more than basic fields?
		if( !$no_meta ) {
			$sql_full = 'title, post_excerpt, permalink, published_date, modified_date_entered, partner_name, '; // must end with comma (,)
		}
		$sql = 'SELECT SQL_CALC_FOUND_ROWS ID, '.$sql_full.'modified_date, is_publicly_published, is_deleted FROM '.self::get_table('oai') .' '.$sql_where_full;
		$sql .= ' ORDER BY modified_date ASC ';

		if( $limit ) {
			if( $offset ) {
				$sql .= 'LIMIT '.intval($offset). ', '.intval($limit);
			} else {
				$sql .= 'LIMIT '.intval($limit);
			}
		}
		$results = $wpdb->get_results($sql);
		
		// Immediately retrieve the total amount of rows
		$sql = 'SELECT FOUND_ROWS() AS ttl;';
		$found_rows_obj = $wpdb->get_results($sql);
		$total_rows = $found_rows_obj[0]->ttl;

		$record_data = array();
		$statement_result = array();
		
		if( isset( $id_set )) {
			$set = $this->post_type_convert_to_external($id_set);
		}
		
		foreach( $results as $result ) {
			$statement_result[] = array(
						'record_id'		=> self::$set_prefix.':'.$set.':'.$result->ID, 
						'modified'		=> $this->helper_convertdate($result->modified_date), 
						'repository_id'	=> $set, 
						'published'		=> $result->is_publicly_published,
						'deleted'		=> $result->is_deleted,
						'metadata'		=> $this->get_meta( $result, $no_meta, $metadataFormat ),
			);			
		}

		$record_data  = array('status' => 'success', 'total' => $total_rows, 'records' => $statement_result);
		
		return $record_data;
	}
}
