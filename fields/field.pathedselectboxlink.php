<?php
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	require_once(EXTENSIONS . '/selectbox_link_field/fields/field.selectbox_link.php');
	
	class FieldPathedSelectBoxLink extends FieldSelectBox_Link {
	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/
		
		public function __construct(&$parent) {
			parent::__construct($parent);
			
			$this->_name = __('Pathed Select Box Link');
			$this->_required = true;
			
			// Set default
			$this->set('show_column', 'no'); 
			$this->set('required', 'yes');
			$this->set('limit', 20);
		}
		
	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/
		
		private function __findPrimaryFieldValueFromRelationID($entry_id) {
			$field_id = $this->findFieldIDFromRelationID($entry_id);
			$primary_field = $this->Database->fetchRow(0, sprintf(
				"
					SELECT
						f.*, s.handle AS `section_handle`
					FROM
						`tbl_fields` AS `f`
					INNER JOIN
						`tbl_sections` AS `s`
						ON s.id = f.parent_section
					WHERE
						f.id = %d
					ORDER BY
						f.sortorder ASC
					LIMIT 1
				",
				$field_id
			));
			
			if (!$primary_field) return NULL;

			$field = $this->_Parent->create($primary_field['type']);
			$data = $this->Database->fetchRow(0, sprintf(
				"
					SELECT
						*
					FROM
						`tbl_entries_data_%d` AS d
					WHERE
						d.entry_id = %d
					ORDER BY
						d.id DESC
					LIMIT 1
				",
				$field_id,
				$entry_id
			));
			
			if (empty($data)) return null;
			
			$primary_field['value'] = $field->prepareTableValue($data); 
			
			return $primary_field;
		}
		
		public function findOptions(array $existing_selection = null, $current_entry_id = null) {
			$limit = $this->get('limit');
			$related = $this->get('related_field_id');
			$values = array();
			
			if (is_array($related) && !empty($related)) {
				foreach ($this->get('related_field_id') as $field_id) {
					$section = $this->Database->fetchRow(0, sprintf(
						"
							SELECT
								s.name, s.id
							FROM
								`tbl_sections` AS `s`
							LEFT JOIN
								`tbl_fields` AS `f`
								ON s.id = f.parent_section
							WHERE
								f.id = %d
							LIMIT 1
						",
						$field_id
					));
					$group = array(
						'name'		=> $section['name'],
						'section'	=> $section['id'],
						'values'	=> array()
					);
					$results = $this->Database->fetchCol('entry_id', sprintf(
						"
							SELECT DISTINCT
								f.entry_id
							FROM
								`tbl_entries_data_%d` AS f
							ORDER BY
								f.entry_id DESC
							LIMIT 0, %d
						",
						$field_id,
						$limit
					));
					
					if (!is_null($existing_selection) && !empty($existing_selection)) {
						foreach ($existing_selection as $key => $entry_id) {
							$x = $this->findFieldIDFromRelationID($entry_id);
							
							if ($x == $field_id) $results[] = $entry_id;
						}
					}
					
					if (is_array($results) && !empty($results)) {
						foreach ($results as $entry_id) {
							if ($entry_id == $current_entry_id) continue;
							
							$value = $this->findPath($entry_id);
							
							$group['values'][$entry_id] = $value;
						}
					}
					
					natcasesort($group['values']);

					$values[] = $group;
				}
			}
			
			return $values;
		}
		
		protected function findPath($entry_id) {
			$value = ''; $data = $this->__findPrimaryFieldValueFromRelationID($entry_id);
			$parent_id = $this->Database->fetchVar('relation_id', 0, sprintf(
				"
					SELECT
						d.relation_id
					FROM
						`tbl_entries_data_%d` AS d
					WHERE
						d.entry_id = %d
					ORDER BY
						d.id DESC
					LIMIT 1
				",
				$this->get('id'),
				$entry_id
			));
			
			if ($parent_id) $value = $this->findPath($parent_id) . ' / ';
			
			return $value . $data['value'];
		}
		
	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
		
		public function displayPublishPanel(&$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$handle = $this->get('element_name');
			$entry_ids = array();
			
			if (!is_null($data['relation_id'])) {
				if (!is_array($data['relation_id'])) {
					$entry_ids = array($data['relation_id']);
				}
				
				else {
					$entry_ids = array_values($data['relation_id']);
				}
			}
			
			$states = $this->findOptions($entry_ids, $entry_id);
			$options = array();
			
			if ($this->get('required') != 'yes') $options[] = array(null, false, null);
			
			if (!empty($states)) {
				foreach ($states as $s) {
					$group = array('label' => $s['name'], 'options' => array());
					
					foreach ($s['values'] as $id => $v) {
						$group['options'][] = array($id, in_array($id, $entry_ids), $v);
					}
					
					$options[] = $group;
				}
			}
			
			$fieldname = "fields{$prefix}[{$handle}]{$postfix}";
			
			if ($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';
			
			$label = Widget::Label($this->get('label'));
			$select = Widget::Select("fields{$prefix}[{$handle}]{$postfix}", $options);
			
			if ($this->get('allow_multiple_selection') == 'yes') {
				$select->setAttribute('multiple', 'multiple');
			}
			
			$label->appendChild($select);
			
			if ($error != null) {
				$label = Widget::wrapFormElementWithError($label, $error);
			}
			
			$wrapper->appendChild($label);
		}
		
	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/
		
		public function fetchIncludableElements() {
			return array(
				$this->get('element_name'),
				$this->get('element_name') . ': recursive'
			);
		}
		
		public function appendFormattedElement(&$wrapper, $data, $encode = false, $mode = null) {
			if (!is_array($data) or empty($data)) return;
			
			$list = new XMLElement($this->get('element_name'));
			
			if (!is_array($data['relation_id'])) $data['relation_id'] = array($data['relation_id']);
			
			@header('content-type: text/plain');
			
			foreach ($data['relation_id'] as $id) {
				$field = $this->__findPrimaryFieldValueFromRelationID($id);
				$handle = Lang::createHandle($field['value']);
				$value = $field['value'];
				
				if ($encode) {
					$value = General::sanitize($field['value']);
				}
				
				// List parents
				if ($mode == 'recursive') {
					$this->appendFormattedElementRecursive($list, $data, $encode);
				}
				
				else {
					$item = new XMLElement('item');
					$item->setAttribute('handle', $handle);
					$item->setAttribute('id', $id);
					$item->setValue($value);
					$list->appendChild($item);
				}
			}
			
			$wrapper->appendChild($list);
		}
		
		protected function appendFormattedElementRecursive($wrapper, $data, $encode) {
			if (!is_array($data) or empty($data)) return;
			
			if (!is_array($data['relation_id'])) $data['relation_id'] = array($data['relation_id']);
			
			foreach ($data['relation_id'] as $id) {
				$field = $this->__findPrimaryFieldValueFromRelationID($id);
				$handle = Lang::createHandle($field['value']);
				$value = $field['value'];
				
				if ($encode) {
					$value = General::sanitize($field['value']);
				}
				
				$item = new XMLElement('item');
				$item->setAttribute('handle', $handle);
				$item->setAttribute('id', $id);
				$item->setAttribute('value', $value);
				
				foreach ($this->get('related_field_id') as $field_id) {
					$data = $this->Database->fetchRow(0, sprintf(
						"
							SELECT
								a.*, b.relation_id
							FROM
								`tbl_entries_data_%d` AS a
							LEFT JOIN
								`tbl_entries_data_%d` AS b
								ON b.entry_id = a.entry_id
							WHERE
								a.entry_id = %d
						",
						$field_id,
						$this->get('id'),
						$id
					));
					
					if (is_null($data) or empty($data)) return;
					
					$this->appendFormattedElementRecursive($item, $data, $encode);
				}
				
				$wrapper->appendChild($item);
			}
		}
		
		public function prepareTableValue($data, XMLElement $link = null) {
			$output = ''; $results = array();
			
			if (!isset($data['relation_id']) or empty($data['relation_id'])) {
				return parent::prepareTableValue(NULL);
			}
			
			if (!is_array($data['relation_id'])) {
				$data['relation_id'] = array($data['relation_id']);
			}
			
			foreach ($data['relation_id'] as $relation_id) {
				$value = $this->findPath($relation_id);
				$section = $this->Database->fetchVar('handle', 0, sprintf(
					"
						SELECT
							s.handle
						FROM
							`tbl_entries` AS e
						LEFT JOIN
							`tbl_sections` AS s
							ON e.section_id = s.id
						WHERE
							e.id = %d
					",
					$relation_id
				));
							
				$results[$relation_id] = array(
					'value'		=> $value,
					'section'	=> $section
				);
			}
			
			foreach ($results as $relation_id => $item) {
				$element = Widget::Anchor(
					$item['value'],
					sprintf(
						'%s/symphony/publish/%s/edit/%d/',
						URL, $item['section'], $relation_id
					)
				);
				
				$output .= $element->generate() . ' ';
			}
			
			$output = trim($output);
			
			if ($link instanceof XMLElement) {
				$link->setValue(General::sanitize(strip_tags($output)));
				
				return $link->generate();
			}
			
			return $output;
		}
	}
	
?>