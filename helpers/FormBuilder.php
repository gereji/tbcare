<?php

namespace helpers;

class FormBuilder {
		
	private $definition = NULL;
	
	private $action = NULL;
	
	private $flow = NULL;
	
	private $name = NULL;
	
	private $sandbox = NULL;
	
	private $content = NULL;
	
	private $records = NULL;
	
	public function __construct(&$sandbox){
		$this->sandbox = &$sandbox;
	}
	
	public function setSource($filename){
		if(!is_readable($filename)) {
			throw new HelperException("'$filename' is not readable");
		}
		$this->definition = simplexml_load_file($filename);
		if(!$this->definition) {
			throw new HelperException("'$filename' is not a valid XML form definition");
		}
		if(!property_exists($this->definition, "fieldset") && !property_exists($this->definition, "field")) {
			throw new HelperException("No fields defined for form '$filename'");
		}
		$this->name = (string) $this->definition->attributes()->name;
		$action = is_null($this->action) ? $this->sandbox->getMeta('URI') : $this->action;
		$this->setAction($action);
		$this->initFlow();
	}
	
	private function initFlow(){
		$base = $this->sandbox->getMeta('base');
		require_once("$base/helpers/Flow.php");
		$id = (string) $this->definition->attributes()->id;
		$name = strlen($id) ? $id : $this->name;
		$filename = "$base/apps/content/flows/$name.xml";
		if(is_file($filename)){
			$this->flow = new Flow($this->sandbox);
			$this->flow->setSource($filename);
		}
	}	
	
	public function getDefinition(){
		return $this->definition;
	}
	
	public function setDefinition(&$definition){
		$this->definition = &$definition;
	}

	public function setAction($action){
		$this->action = $action;
	}
	
	public function getAction(){
		return $this->action;
	}
	
	public function asHTML(){
		$translator = $this->sandbox->getHelper('translation');
		$header = $translator->translate($this->name);
		$class = (string) $this->definition->attributes()->class;
		$class = strlen($class) ? $class : $this->name;
		$action = "action=\"$this->action\"";
		$html[] = "<form $action name=\"$this->name\" method=\"POST\" title=\"$header\" class=\"$class\">";
		if(property_exists($this->definition, "fieldset")){
			foreach($this->definition->fieldset as $fieldset){
				$name = (string) $fieldset->attributes()->name;
				$class = (string) $fieldset->attributes()->class;
				$class = strlen($class) ? $class : $name;
				$html[] = "\t<fieldset name=\"$name\" class=\"$class\">";
				$legend = $translator->translate($fieldset->attributes()->legend);
				$html[] = "\t\t<legend>$legend</legend>";
				$html[] = $this->createFields($fieldset->field);
				$html[] = $this->createButtons($fieldset);
				$html[] = "\t</fieldset>";
			}
		} else if(property_exists($this->definition, "field")) {
			$html[] = $this->createFields($this->definition->field);
			$html[] = $this->createButtons($this->definition);
		} else {
			throw new HelperException("No fields defined for form '$this->name'");
		}
		$html[] = "</form>";
		return join("\n", $html);
	}
	
	protected function createFields($fields){
		foreach($fields as $field){
			$html[] = $this->createField($field);
		}
		return join("\n", $html);
	}
	
	protected function createField($field){
		$translator = $this->sandbox->getHelper('translation');
		foreach($field->element as $element){
			$type = (string) $element->attributes()->type;
			$label = $translator->translate($element->attributes()->label);
			if($type == 'options'){
				$html[] = $this->createElement($field, $element, $type);
			} else {
				$labelClass = $this->getClasses($element);
				$html[] = "\t\t<label $labelClass>";
				$html[] = "\t\t\t<span>$label</span>";
				$html[] = $this->createElement($field, $element, $type);
				$html[] = "\t\t</label>";
			}
		}
		return join("\n", $html);
	}
	
	protected function createElement($field, $element, $type){
		switch($type){
			case "text":
				return "\t\t\t".$this->createInputText($field, $element);
			break;
			case "password":
				return "\t\t\t".$this->createInputPassword($field, $element);
			break;
			case "radio":
				return "\t\t\t".$this->createInputRadio($field, $element);
			break;
			case "checkbox":
				return "\t\t\t".$this->createInputCheckbox($field, $element);
			case "options":
				return "\t\t\t".$this->createCheckboxOptions($field, $element);
			break;
			case "hidden":
				return "\t\t\t".$this->createInputHidden($field, $element);
			break;
			case "select":
				return "\t\t\t".$this->createSelect($field, $element);
			break;
			case "textarea":
				return "\t\t\t".$this->createTextarea($field, $element);
			break;
		}
	}
		
	protected function createInputText(&$field, &$element){
		$name = $this->getAttribute($field, 'name');
		$value = $this->elementValue($element);
		$translator = $this->sandbox->getHelper('translation');
		$placeholder = $translator->translate($element->attributes()->placeholder);
		$attributes = $this->maxLength($field);
		$attributes .= $this->getClasses($element);
		$attributes .= strlen($placeholder) ? ' placeholder="'.$placeholder.'"' : $attributes;
		return "<input type=\"text\" name=\"$name\" value=\"$value\"$attributes/>";
	}
	
	protected function createInputPassword(&$field, &$element){
		$name = $this->getAttribute($field, 'name');
		$attributes = $this->maxLength($field);
		$attributes .= $this->getClasses($element);
		return "<input type=\"password\" name=\"$name\"$attributes/>";
	}
	
	protected function createInputRadio(&$field, &$element){
		$name = $this->getAttribute($field, 'name');
		$value = $this->elementValue($element);
		$attributes = $this->getClasses($element);
		return "<input type=\"radio\" name=\"$name\" value=\"$value\"$attributes/>";
	}

	protected function createInputCheckbox(&$field, &$element){
		$name = $this->getAttribute($field, 'name');
		$value = $this->elementValue($element);
		$attributes = $this->getClasses($element);
		return "<input type=\"checkbox\" name=\"$name\" value=\"$value\"$attributes/>";
	}
	
	protected function createInputHidden(&$field, &$element){
		$name = $this->getAttribute($field, 'name');
		$attributes = $this->maxLength($field);
		$attributes .= $this->getClasses($element);
		$value = $this->elementValue($element);
		return "<input type=\"hidden\" value=\"$value\" name=\"$name\" $attributes/>";
	}	
		
	protected function createSelect(&$field, &$element){
		$translator = $this->sandbox->getHelper('translation');
		$placeholder = $translator->translate($element->attributes()->placeholder);
		$name = $this->getAttribute($field, 'name');
		$class = $this->getClasses($element);
		$html[] = "<select name=\"$name\"$class>";
		if(strlen($placeholder)){
			$html[] = "\t\t\t\t<option value=\"0\">$placeholder</option>";
		}
		$table = (string) $element->attributes()->lookup;
		$default = (integer) $element->attributes()->select;
		if(strlen($table)){
			$value = (string) $element->attributes()->value;
			$display = (string) $element->attributes()->display;
			$options =$this->getStorage()->select(array("table" => $table, 'constraints' => array('inTrash' => 'No')));
			if($options){
				foreach($options as $option){
					$select = $default == $option[$value] ? ' selected="selected"' : '';
					$html[] = "\t\t\t\t".'<option value="'.$option[$value].'"'.$select.'>'.$option[$display].'</option>';
				}
			}
		}
		$html[] = "\t\t\t</select>";
		return join("\n", $html);
	}
	
	protected function createCheckboxOptions(&$field, &$element){
		$options = $this->getOptions($element);
		if($options == NULL) return;
		$translator = $this->sandbox->getHelper('translation');
		$labelClass = $this->getClasses($element);
		$name = (string) $element->attributes()->name;
		$label = (string) $element->attributes()->display;
		$value = (string) $element->attributes()->value;
		$class = $this->getClasses($element);
		foreach($options as $option){
			$html[] = "<label $labelClass>";
			$title = $translator->translate($option[$label]);
			$title = strlen($title) ? $title : $translator->translate($option[$label].'.label');
			$title = strlen($title) ? $title : $option[$label];
			$html[] = "\t\t\t".'<span>' . $title . '</span>';
			$html[] = "\t\t\t".'<input type="checkbox" name="'.$name.'[]" value="'.$option[$value].'"' . $class . '/>';
			$html[] = "\t\t</label>";
		}
		return implode("\n", $html);
	}
	
	protected function getOptions(&$element){
		$table = (string) $element->attributes()->lookup;
		$select['table'] = $table;
		$filter = (string) $element->attributes()->filter;
		if(strlen($filter)){
			$select['constraints'] = json_decode($filter);
		}
		return $this->getStorage()->select($select);
	}
	
	protected function createTextarea(&$field, &$element){
		$name = (string) $field->attributes()->name;
		$value = $this->elementValue($element);
		return "<textarea name=\"$name\">$value</textarea>";
	}
	
	protected function createButtons(&$buttons){
		$html = array();
		foreach($buttons->button as $button){
			$type = (string) $button->attributes()->type;
			switch($type){
				case "submit":
					$html[] = "\t\t".$this->createButtonSubmit($button);
					break;
				case "button":
					$html[] = "\t\t".$this->createButton($button);
					break;							
				case "reset":
					$html[] = "\t\t".$this->createButtonReset($button);
					break;
			}
		}
		return join("\n", $html);
	}
	
	protected function createButtonSubmit($button){
		$translator = $this->sandbox->getHelper('translation');
		$name = $this->getAttribute($button, 'name');
		$value = $translator->translate($button->attributes()->value);
		$classes = $this->getClasses($button);
		return "<input type=\"submit\" name=\"$name\" value=\"$value\" $classes/>";
	}	

	protected function createButton($button){
		$translator = $this->sandbox->getHelper('translation');
		$name = $this->getAttribute($button, 'name');
		$value = $translator->translate($button->attributes()->value);
		$classes = $this->getClasses($button);
		return "<input type=\"button\" name=\"$name\" value=\"$value\" $classes/>";
	}
	
	protected function createButtonReset($button){
		$translator = $this->sandbox->getHelper('translation');
		$name = $this->getAttribute($button, 'name');
		$value = $translator->translate($button->attributes()->value);
		$classes = $this->getClasses($button);
		return "<input type=\"reset\" name=\"$name\" value=\"$value\" $classes/>";
	}
	
	protected function getAttribute($node, $property){
		$class = $node->attributes();
		if(property_exists($class, $property)){
			return (string) $class->$property; 
		} else {
			return "";
		}
	}
	
	protected function elementValue($element){
		$attributes = $element->attributes();
		if(property_exists($attributes, "value")){
			return (string) $attributes->value;
		} else {
			return '{{'.(string) $attributes->name.'}}';
		}
	}
	
	protected function getClasses($element){
		if(property_exists($element, 'class')){
			foreach($element->class as $class){
				$classes[] = (string) $class;
			}
			return " class=\"".join(" ", $classes)."\"";
		} else {
			return "";
		}
	}
	
	protected function maxLength($field){
		$attributes = $field->attributes();
		if(property_exists($attributes, "length")){
			$length = (string) $attributes->length;
			return " maxlength=\"$length\"";
		} else {
		return "";
		}
	}
	
	public function getStorage(){
		$storage = (string) $this->definition->attributes()->storage;
		switch($storage){
			case "global":
				return $this->sandbox->getGlobalStorage();
				break;
			case "parent":
				return $this->sandbox->getParentStorage();
				break;
			case "local":
				return $this->sandbox->getLocalStorage();
				break;
			default:
				return $this->sandbox->getLocalStorage();
				break;				
		}
	}
	
	public function createRecord(){
		if(!$this->flow->isInsertable()) throw new HelperException('data access violation');;
		$input = $this->sandbox->getHelper('input');
		$name = (string) $this->definition->attributes()->name;
		$record['table'] = $name;
		$fields = $this->getFields();
		foreach($fields as $field){
			$type = (string) $field->attributes()->type;
			$key = (string) $field->attributes()->name;
			if(strlen($type) && $type != "options") {
				$record['content'][$key] = $this->getContent($key);
			}
		}
		try {
			$insertID = $this->getStorage()->insert($record);
			$this->createOptions($insertID);
			return json_encode(array("success" => $insertID), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		}catch(HelperException $e){
			throw new \apps\ApplicationException($e->getMessage());
		}
	}
		
	private function createOptions($insertID){
		$fields = $this->getSpecialFields('options');
		if(!$fields) return;
		$name = (string) $this->definition->attributes()->name;
		foreach($fields as $field){
			$key = (string) $field->attributes()->name;
			if(!array_key_exists($key, $_POST)) continue;
			$join = (string) $field->attributes()->join;
			foreach($_POST[$key] as $option){
				if((integer) $option > 0){
					$insert['table'] = $join;
					if((string) $this->definition->attributes()->storage != 'local'){
						$content['site'] = $this->sandbox->getHelper('site')->getID();
					}
					$content[$key] = $option;
					$content['creationTime'] = time();
					$content[$name] = $insertID;
					$insert['content'] = $content;
					$this->getStorage()->insert($insert);
				}
			}
		}
	}	
	
	public function getContent($key){
		if(in_array($key, array('creationTime', 'site', 'user', 'sourceIP'))){
			switch($key){
				case "creationTime":
					return time();
					break;
				case "site":
					return $this->sandbox->getHelper('site')->getID();
					break;
				case "user":
					return $this->sandbox->getHelper('user')->getID();
					break;
				case "sourceIP":
					return array_key_exists('REMOTE_ADDR', $_SERVER) ? $_SERVER['REMOTE_ADDR'] : "127.0.0.1";
					break;
			}
		}else{
			return $this->sandbox->getHelper('input')->postString($key);
		}
	}
	
	public function selectRecord(){
		if(!$this->flow->isSelectable()) throw new HelperException('data access violation');
		$primarykey = $this->sandbox->getHelper('input')->postInteger('primarykey');
		if(!$primarykey) return;
		$key = (string) $this->definition->attributes()->primarykey;
		$table = (string) $this->definition->attributes()->name;
		$columns = $this->getColumns();
		$sql = sprintf("SELECT %s, %s FROM `%s` WHERE `%s` = %d", $key, implode(", ", $columns), $table, $key, $primarykey);
		$this->records = $this->getOptionValues($this->getStorage()->query($sql));
		$this->formatRecords();
		return json_encode($this->records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);		
	}
	
	private function formatRecords(){
		if(!$this->records) return;
		$settings = $this->sandbox->getHelper('site')->getSettings();
		foreach($this->records as $key => $record){
			if(array_key_exists('creationTime', $record)){
				$this->records[$key]['creationTime'] = date($settings['timeformat'], $record['creationTime']);
			}
			if(array_key_exists('expiryTime', $record)){
				$this->records[$key]['expiryTime'] = date($settings['timeformat'], $record['expiryTime']);
			}
		}
	}	
	
	protected function getOptionValues($rows){
		$fields = $this->getSpecialFields('options');
		if(!$fields) return $rows;
		$table = (string) $this->definition->attributes()->name;
		$storage = (string) $this->definition->attributes()->storage;
		$key = (string) $this->definition->attributes()->primarykey;
		foreach($fields as $field){
			$join = (string) $field->attributes()->join;
			$name = (string) $field->attributes()->name;
			if(strlen($join)){
				$select['table'] = $join;
				$constraints[$table] = $rows[0][$key];
				if($storage != 'local'){
					$constraints['site'] = $this->sandbox->getHelper('site')->getID();
				}
				$select['constraints'] = $constraints;
				$records = $this->getStorage()->select($select);
				if($records){
					$values = array();
					foreach($records as $record){
						$values[] = $record[$name];
					}
					$rows[0][$name] = implode(', ', $values);
				}
			}
		}
		return $rows;
	}
	
	public function updateRecord(){
		if(!$this->flow->isUpdateable()) throw new HelperException('data access violation');;
		if(!array_key_exists('primarykey', $_POST)) return;
		$columns = $this->getColumns();
		$key = (string) $this->definition->attributes()->primarykey;
		$update['table'] = (string) $this->definition->attributes()->name;
		$ID = $this->getStorage()->sanitize($_POST['primarykey']);
		$update['constraints'][$key] = $ID;
		if((string) $this->definition->attributes()->storage != 'local'){
			$update['constraints']['site'] = $this->sandbox->getHelper('site')->getID();
		}
		foreach ($columns as $column) {
			$update['content'][$column] = $this->sandbox->getHelper('input')->postString($column);
		}
		$result['success'] = $this->getStorage()->update($update);
		$this->updateOptions($this->getOptionValues(array(array($key => $ID))));
		return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);		
	}
	
	private function updateOptions($optionValues){
		$fields = $this->getSpecialFields('options');
		if(!$fields) return;
		$table = (string) $this->definition->attributes()->name;
		$primarykey = (string) $this->definition->attributes()->primarykey;
		$siteID = $this->sandbox->getHelper('site')->getID();
		$content['site'] = $siteID;
		foreach($fields as $field){
			$name = (string) $field->attributes()->name;
			if(!array_key_exists($name, $_POST)) continue;
			$records = array_key_exists($name, $optionValues[0]) ? $optionValues[0][$name] : NULL;
			$join = (string) $field->attributes()->join;
			$dataScope = (string) $this->definition->attributes()->storage == 'local' ? "" : sprintf("AND `site` = %d", $siteID);
			$this->getStorage()->query(sprintf("DELETE FROM `%s` WHERE `%s` = %d %s AND `%s` NOT IN (%s)", $join, $table, $optionValues[0][$primarykey], $dataScope, $name, implode(', ', $_POST[$name])));
			foreach($_POST[$name] as $option){
				$record = (integer) $option;
				if($record == 0) continue;
				if(in_array($record, explode(', ', $records))) continue;
				if((string) $this->definition->attributes()->storage != 'local'){
					$content['site'] = $siteID;
				}
				$insert['table'] = $join;
				$content[$name] = $record;
				$content[$table] = $optionValues[0][$primarykey];
				$content['creationTime'] = time();
				$insert['content'] = $content;
				$this->getStorage()->insert($insert);
			}
		}
	}
	
	public function deleteRecord(){
		if(!$this->flow->isDeleteable()) throw new HelperException('data access violation');
		$primarykey = (string) $this->definition->attributes()->primarykey;
		$primaryvalue = $this->sandbox->getHelper('input')->postInteger('primarykey');
		if(!$primaryvalue) throw new HelperException('No valid primaryvalue found '.json_encode($_POST));
		$update['table'] = $this->name;
		$update['content']['inTrash'] = 'Yes';
		$update['constraints'][$primarykey] = $primaryvalue;
		$result['success'] = $this->getStorage()->update($update);
		return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);	
	}
	
	protected function getColumns(){
		$fields = $this->getFields();
		foreach($fields as $field){
			$type = (string) $field->attributes()->type;
			if(strlen($type) && $type != 'options'){
				$columns[] = (string) $field->attributes()->name;				
			}
		}
		return $columns;
	}	
	
	protected function getFields(){
		if(property_exists($this->definition, "fieldset")){
			foreach($this->definition->fieldset as $fieldset){
				foreach($fieldset->field as $field){
					$fields[] = $field;
				}
			}
		} else if (property_exists($this->definition, "field")) {
			foreach ($this->definition->field as $field) {
				$fields[] = $field;
			}
		} else {
			throw new HelperException("No fields defined for form : ".$this->name);
		}
		return $fields;
	}
	
	protected function getSpecialFields($type){
		$fields = $this->getFields();
		foreach($fields as $field){
			if((string) $field->attributes()->type == $type){
				$result[] = $field;
			}
		}
		return isset($result) ? $result : false;
	}

	public function setContent($content){
		$this->content = $content;
	}
	
	public function populate(){
		foreach($this->content as $key => $value){
			foreach($this->definition->field as $field){
				if((string) $field->attributes()->name != $key) continue;
				foreach($field->element as $element){
					$name = (string) $element->attributes()->name;
					if($name != $key) continue;
					$element->addAttribute('value', $value);
				}
			}
		}
	}
}