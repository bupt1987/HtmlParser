<?php
namespace HtmlParser;

/**
 * Copyright (c) 2013, 俊杰Jerry, Shinbon Lin
 * All rights reserved.
 *
 * @description: html解析器
 * @author     : 俊杰Jerry<bupt1987@gmail.com>
 * @author     : Shinbon Lin 
 * @date       : 2013-6-10
 */
class ParserDom {

	/**
	 * @var \DOMNode
	 */
	public $node;

	/**
	 * @var array
	 */
	private $_lFind = array();

	/**
	 * @param \DOMNode|string $node
	 * @throws \Exception
	 */
	public function __construct($node = null) {
		if ($node !== null) {
			if ($node instanceof \DOMNode) {
				$this->node = $node;
			} else {
				$dom = new \DOMDocument();
				$dom->preserveWhiteSpace = false;
				$dom->strictErrorChecking = false;
				if (@$dom->loadHTML($node)) {
					$this->node = $dom;
				} else {
					throw new \Exception('load html error');
				}
			}
		}
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __destruct() {
		$this->clearNode($this->node);
	}

	/**
	 * 广度优先查询
	 *
	 * @param string $selector
	 * @param number $idx 找第几个,从0开始计算，null 表示都返回, 负数表示倒数第几个
	 * @return ParserDom|ParserDom[]
	 */
	/*public function findBreadthFirst($selector, $idx = null) {
		if (empty($this->node->childNodes)) {
			return false;
		}
		$selectors = $this->parse_selector($selector);
		if (($count = count($selectors)) === 0) {
			return false;
		}
		$found = array();
		for ($c = 0; $c < $count; $c++) {
			if (($level = count($selectors [$c])) === 0) {
				return false;
			}
			$need_to_search = iterator_to_array($this->node->childNodes);
			$search_level = 1;
			while (!empty($need_to_search)) {
				$temp = array();
				foreach ($need_to_search as $search) {
					if ($search_level >= $level) {
						$rs = $this->seek($search, $selectors [$c], $level - 1);
						if ($rs !== false && $idx !== null) {
							if ($idx == count($found)) {
								return new self($rs);
							} else {
								$found[] = new self($rs);
							}
						} elseif ($rs !== false) {
							$found[] = new self($rs);
						}
					}
					$temp[] = $search;
					array_shift($need_to_search);
				}
				foreach ($temp as $temp_val) {
					if (!empty($temp_val->childNodes)) {
						foreach ($temp_val->childNodes as $val) {
							$need_to_search[] = $val;
						}
					}
				}
				$search_level++;
			}
		}
		if ($idx !== null) {
			if ($idx < 0) {
				$idx = count($found) + $idx;
			}
			if (isset($found[$idx])) {
				return $found[$idx];
			} else {
				return false;
			}
		}
		return $found;
	}*/


	/**
	 * 深度优先查询
	 *
	 * @param string $selector
	 * @param number $idx 找第几个,从0开始计算，null 表示都返回, 负数表示倒数第几个
	 * @return self|self[]
	 */
	public function find($selector, $idx = null) {
		if (empty($this->node->childNodes)) {
			return false;
		}
		$selectors = $this->parse_selector($selector);
		if (($count = count($selectors)) === 0) {
			return false;
		}
		for ($c = 0; $c < $count; $c++) {
			if (($level = count($selectors [$c])) === 0) {
				return false;
			}
			$this->search($this->node, $idx, $selectors [$c], $level);
		}
		$found = $this->_lFind;
		$this->_lFind = array();
		if ($idx !== null) {
			if ($idx < 0) {
				$idx = count($found) + $idx;
			}
			if (isset($found[$idx])) {
				return $found[$idx];
			} else {
				return false;
			}
		}
		return $found;
	}

	/**
	 * 返回文本信息
	 *
	 * @return string
	 */
	public function getPlainText($value = null) {
		if ($value == null) {
			return $this->text($this->node);
		} else {
			$this->node->nodeValue = mb_convert_encoding($value, 'HTML-ENTITIES', 'UTF-8');
		}
	}

	/**
	 * 获取innerHtml
	 * @param string $value
	 * @return string
	 */
	public function innerHtml($value = null) {
		if ($value == null) {
			if ( !is_null($value) ) {
				// give a empty string, you want me destory this element!
				for ($x = $this->node->childNodes->length-1; $x >= 0; $x--) {
					$this->node->removeChild($this->node->childNodes->item($x));
				}
			} else {
				$innerHTML = "";
				$children = $this->node->childNodes;
				foreach ($children as $child) {
					$innerHTML .= $this->node->ownerDocument->saveHTML($child) ?: '';
				}
				return $innerHTML;
			}
		} else {
			// Original code by Keyvan Minoukadeh <keyvan@keyvan.net>,
			// integrated by Shinbon Lin.

			for ($x = $this->node->childNodes->length-1; $x >= 0; $x--) {
				$this->node->removeChild($this->node->childNodes->item($x));
			}
			$f = $this->node->ownerDocument->createDocumentFragment();
			$result = @$f->appendXML($value); // @ to suppress PHP warnings
			if ($result) {
				if ($f->hasChildNodes()) {
					$this->node->appendChild($f);
				}
			} else {
				// $value is probably ill-formed
				// 自定义的容器有html标签语法上的错误
				$f = new \DOMDocument();
				$value = mb_convert_encoding($value, 'HTML-ENTITIES', 'UTF-8');
				$result = @$f->loadHTML('<htmlfragment>'.$value.'</htmlfragment>');
				if ($result) {
					$import = $f->getElementsByTagName('htmlfragment')->item(0);
					foreach ($import->childNodes as $child) {
						$importedNode = $this->node->ownerDocument->importNode($child, true);
						$this->node->appendChild($importedNode);
					}
				} else {
					// empty element.
				}
			}
		}
	}

	/**
	 * 获取outerHtml
	 * @param string $value
	 * @return string|bool
	 */
	public function outerHtml($value = null) {
		if ($value == null) {
			if ( !is_null($value) ) {
				// give a empty string, you want me destory this element!
				$parentNode = $this->getParent($this->node);
				for ($x = $parentNode->childNodes->length-1; $x >= 0; $x--) {
					if ($this->node->isSameNode($parentNode->childNodes->item($x))) {
						$parentNode->removeChild($parentNode->childNodes->item($x));
					}
				}
			} else {
				$doc = new \DOMDocument();
				$doc->appendChild($doc->importNode($this->node, true));
				return $doc->saveHTML($doc);
			}
		} else {

			$parentNode = $this->getParent($this->node);
			for ($x = $parentNode->childNodes->length-1; $x >= 0; $x--) {
				if ($this->node->isSameNode($parentNode->childNodes->item($x))) {
					$f = $parentNode->ownerDocument->createDocumentFragment();
					$result = @$f->appendXML($value);
					if ($result) {
						if ($parentNode->hasChildNodes()) {
							$parentNode->replaceChild($f, $parentNode->childNodes->item($x));
						}
					} else {
						// $value is probably ill-formed
						// 自定义的容器有html标签语法上的错误
						$f = new \DOMDocument();
						$value = mb_convert_encoding($value, 'HTML-ENTITIES', 'UTF-8');
						$result = @$f->loadHTML($value);
						if ($result) {
							$this->strip_html_container($f);
							$import = $f->documentElement;
							$importedNode = $this->node->ownerDocument->importNode($import, true);
							if ($parentNode->hasChildNodes()) {
								$parentNode->replaceChild($importedNode, $parentNode->childNodes->item($x));
							}
						} else {
							// empty element.
						}
					}
				}
			}
		}
	}


	/**
	 * 获取html的元属值
	 *
	 * @param string $name
	 * @return string|null
	 */
	public function getAttr($name) {
		//$oAttr = $this->node->attributes->getNamedItem($name);
		$oAttr = $this->node->getAttribute($name);

		if (isset($oAttr)) {
			//return $oAttr->nodeValue;
			return $oAttr;
		}
		return null;
	}

	/**
	 * 设置html的元属值
	 *
	 * @param string $name
	 * @param string $value
	 * @return string|null
	 */
	public function setAttr($name, $value) {
		if ($value === null) {
			$this->node->removeAttribute($name);
		} else {
			$this->node->setAttribute($name, $value);
		}
		return null;
	}

	/**
	 * 匹配
	 *
	 * @param string $exp
	 * @param string $pattern
	 * @param string $value
	 * @return boolean|number
	 */
	private function match($exp, $pattern, $value) {
		$pattern = strtolower($pattern);
		$value = strtolower($value);
		switch ($exp) {
			case '=' :
				return ($value === $pattern);
			case '!=' :
				return ($value !== $pattern);
			case '^=' :
				return preg_match("/^" . preg_quote($pattern, '/') . "/", $value);
			case '$=' :
				return preg_match("/" . preg_quote($pattern, '/') . "$/", $value);
			case '*=' :
				if ($pattern [0] == '/') {
					return preg_match($pattern, $value);
				}
				return preg_match("/" . $pattern . "/i", $value);
		}
		return false;
	}

	/**
	 * 分析查询语句
	 *
	 * @param string $selector_string
	 * @return array
	 */
	private function parse_selector($selector_string) {
		$pattern = '/([\w-:\*]*)(?:\#([\w-]+)|\.([\w-]+))?(?:\[@?(!?[\w-:]+)(?:([!*^$]?=)["\']?(.*?)["\']?)?\])?([\/, ]+)/is';
		preg_match_all($pattern, trim($selector_string) . ' ', $matches, PREG_SET_ORDER);
		$selectors = array();
		$result = array();
		foreach ($matches as $m) {
			$m [0] = trim($m [0]);
			if ($m [0] === '' || $m [0] === '/' || $m [0] === '//')
				continue;
			if ($m [1] === 'tbody')
				continue;
			list ($tag, $key, $val, $exp, $no_key) = array($m [1], null, null, '=', false);
			if (!empty ($m [2])) {
				$key = 'id';
				$val = $m [2];
			}
			if (!empty ($m [3])) {
				$key = 'class';
				$val = $m [3];
			}
			if (!empty ($m [4])) {
				$key = $m [4];
			}
			if (!empty ($m [5])) {
				$exp = $m [5];
			}
			if (!empty ($m [6])) {
				$val = $m [6];
			}
			// convert to lowercase
			$tag = strtolower($tag);
			$key = strtolower($key);
			// elements that do NOT have the specified attribute
			if (isset ($key [0]) && $key [0] === '!') {
				$key = substr($key, 1);
				$no_key = true;
			}
			$result [] = array($tag, $key, $val, $exp, $no_key);
			if (trim($m [7]) === ',') {
				$selectors [] = $result;
				$result = array();
			}
		}
		if (count($result) > 0) {
			$selectors [] = $result;
		}
		return $selectors;
	}

	/**
	 * 深度查询
	 *
	 * @param \DOMNode $search
	 * @param          $idx
	 * @param          $selectors
	 * @param          $level
	 * @param int $search_level
	 * @return bool
	 */
	private function search(&$search, $idx, $selectors, $level, $search_level = 0) {
		if ($search_level >= $level) {
			$rs = $this->seek($search, $selectors, $level - 1);
			if ($rs !== false && $idx !== null) {
				if ($idx == count($this->_lFind)) {
					$this->_lFind[] = new self($rs);
					return true;
				} else {
					$this->_lFind[] = new self($rs);
				}
			} elseif ($rs !== false) {
				$this->_lFind[] = new self($rs);
			}
		}
		if (!empty($search->childNodes)) {
			foreach ($search->childNodes as $val) {
				if ($this->search($val, $idx, $selectors, $level, $search_level + 1)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * 获取tidy_node文本
	 *
	 * @param \DOMNode $node
	 * @return string
	 */
	private function text(&$node) {
		return $node->textContent;
	}

	/**
	 * 匹配节点,由于采取的倒序查找，所以时间复杂度为n+m*l n为总节点数，m为匹配最后一个规则的个数，l为规则的深度,
	 * @codeCoverageIgnore
	 * @param \DOMNode $search
	 * @param array $selectors
	 * @param int $current
	 * @return boolean|\DOMNode
	 */
	private function seek($search, $selectors, $current) {
		if (!($search instanceof \DOMElement)) {
			return false;
		}
		list ($tag, $key, $val, $exp, $no_key) = $selectors [$current];
		$pass = true;
		if ($tag === '*' && !$key) {
			exit('tag为*时，key不能为空');
		}
		if ($tag && $tag != $search->tagName && $tag !== '*') {
			$pass = false;
		}
		if ($pass && $key) {
			if ($no_key) {
				if ($search->hasAttribute($key)) {
					$pass = false;
				}
			} else {
				if ($key != "plaintext" && !$search->hasAttribute($key)) {
					$pass = false;
				}
			}
		}
		if ($pass && $key && $val && $val !== '*') {
			if ($key == "plaintext") {
				$nodeKeyValue = $this->text($search);
			} else {
				$nodeKeyValue = $search->getAttribute($key);
			}
			$check = $this->match($exp, $val, $nodeKeyValue);
			if (!$check && strcasecmp($key, 'class') === 0) {
				foreach (explode(' ', $search->getAttribute($key)) as $k) {
					if (!empty ($k)) {
						$check = $this->match($exp, $val, $k);
						if ($check) {
							break;
						}
					}
				}
			}
			if (!$check) {
				$pass = false;
			}
		}
		if ($pass) {
			$current--;
			if ($current < 0) {
				return $search;
			} elseif ($this->seek($this->getParent($search), $selectors, $current)) {
				return $search;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * 获取父亲节点
	 *
	 * @param \DOMNode $node
	 * @return \DOMNode
	 */
	private function getParent($node) {
		return $node->parentNode;
	}
	
	/**
	 * 获取Tag名
	 *
	 * @return string
	 */
	public function getTag() {
		return $this->node->nodeName;
	}
	/**
	 * @codeCoverageIgnore
	 * 释放内存 Automatically free memory
	 *
	 * @param $node
	 */
	private function clearNode(&$node) {
		if (!empty($node->childNodes)) {
			foreach ($node->childNodes as $child) {
				$this->clearNode($child);
			}
		}
		unset($node);
	}
	/**
	 * Manually free memory
	 */

	public function clear() {
		$this->clearNode($this->node);
	}
	/**
	 * 将处理后的Dom输出
	 * Export changed Dom into string
	 * 支援 PHP Simple Dom 的习惯用法
	 * for supporting usage of PHP Simple DOM Parser
	 *
	 * @param string $filepath
	 * @param boolean $strip
	 * @return string
	 */
	public function save($filepath = '', $strip = true) {
		// strip <html><body>
		if ($strip) $this->strip_html_container($this->node);
		$ret = $this->node->saveHTML();
		if ($filepath !== '') file_put_contents($filepath, $ret, LOCK_EX);
		return $ret;
	}
	/**
	 * 去除因 loadHTML() 自行添加的外部HTML容器
	 * Strip HTML container created by loadHTML()
	 *
	 * @param \DOMNode $node
	 */
	private function strip_html_container(&$node) {
		$container = $node->getElementsByTagName('body')->item(0);
		$container = $container->parentNode->removeChild($container);
		while ($node->firstChild) {
			$node->removeChild($node->firstChild);
		}
		while ($container->firstChild ) {
			$node->appendChild($container->firstChild);
		}
	}
	/**
	 * Magic methods - for supporting usage of PHP Simple Dom Parser
	 * 支援 PHP Simple Dom 的习惯用法
	 *
	 * @param string $name
	 * @return string
	 */
	function __get($name) {
		switch ($name) {
			case 'outertext':
				return $this->outerHtml();
			case 'innertext':
				return $this->innerHtml();
			case 'plaintext':
				return $this->getPlainText();
			case 'tag':
				return $this->getTag();
			default:
				return $this->getAttr($name);
		}
	}
	/**
	 * Magic methods - for supporting usage of PHP Simple Dom Parser
	 * 支援 PHP Simple Dom 的习惯用法
	 *
	 * @param string $name
	 * @return string
	 */
	function __set($name, $value) {
		switch ($name) {
			case 'outertext':
				$this->outerHtml($value);
				break;
			case 'innertext':
				$this->innerHtml($value);
				break;
			case 'plaintext':
				$this->getPlainText($value);
				break;
			case 'tag':
				break;
			default:
				$this->setAttr($name, $value);
		}
	}
}

?>
