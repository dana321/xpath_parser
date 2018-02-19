<?php
// vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 encoding=utf-8 fdm=marker :
// {{{ Licence
// +--------------------------------------------------------------------------+
// | XPath_Parser															 |
// +--------------------------------------------------------------------------+
// | Copyright (C) 2009 Nicolas Thouvenin, 2018 dana321 |
// +--------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or modify it  |
// | under the terms of the GNU Lesser General Public License as published by |
// | the Free Software Foundation; either version 2.1 of the License, or	  |
// | (at your option) any later version.									  |
// | This library is distributed in the hope that it will be useful, but	  |
// | WITHOUT ANY WARRANTY; without even the implied warranty of			   |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.					 |
// | See the GNU Lesser General Public License for more details.			  |
// |																		  |
// | You should have received a copy of the GNU Lesser General Public License |
// | along with this library; if not, write to the Free Software Foundation,  |
// | Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA			  |
// +--------------------------------------------------------------------------+
// }}}

/**
 * @category  XPath
 * @package   XPath_Parser
 * @author	Nicolas Thouvenin <nthouvenin@gmail.com>
 * @copyright 2009 Nicolas Thouvenin, 2018 dana321
 * @license   http://opensource.org/licenses/lgpl-license.php LGPL
 * @version   SVN: $Id$
 * @link	  http://www.touv.fr/
 */

/**
 * Classe Permettant
 *
 * @category  XPath
 * @package   XPath_Parser
 * @author	Nicolas Thouvenin <nthouvenin@gmail.com>
 * @copyright 2009 Nicolas Thouvenin, 2018 dana321
 * @license   http://opensource.org/licenses/lgpl-license.php LGPL
 */
class XPath_Parser
{
	protected $buffer;
	protected $pointer;
	protected $currentChar;
	protected $nextChar;
	protected $currentString;
	protected $outputArray;
	protected $outputTree;

	/**
	 * Constructeur
	 *
	 * @param string $xpath
	 */
	public function __construct($xpath){
		$this->buffer=$xpath;
		$this->pointer=-1;
		$this->forward();
	}
	protected function forward($i=1){
		$this->pointer+=$i;
		$this->currentChar=isset($this->buffer[$this->pointer]) ? $this->buffer[$this->pointer] :  null;
		$this->nextChar=isset($this->buffer[$this->pointer+1]) ? $this->buffer[$this->pointer+1] : '';
		$this->currentString=substr($this->buffer, $this->pointer);
	}
	public function getLocalization(){
		$a=$this->getArray();
		return '/'.$this->_scan2($a['location']);
	}
	public function getArray(){
		if (is_null($this->outputArray)) {
			$this->outputArray=array();
			$this->location($this->outputArray);
		}
		return $this->outputArray;
	}
	public function getTree(){
		if (is_null($this->outputTree)) {
			$a=$this->getArray();
			$n=new stdClass;
			$n->depth=-1;
			$this->_scan1($a['location'], $n);			
			$this->outputTree=$n->trunk;
		}
		return $this->outputTree;
	}
	private function _scan1(array $a, $z=null){
		$previous=$z;
		foreach($a as $n) {
			$node=new stdClass;
			$node->localName=$n['localName'];
			$node->nodeType=$n['axis'] === 'attribute' ? XMLReader::ATTRIBUTE : XMLReader::ELEMENT;
			if ($n['axis'] !== 'descendant-or-self') {
				$node->depth=$n['axis'] === 'attribute'  ? $previous->depth : ($previous->depth+1);
			}
			else {
				$node->mindepth=$previous->depth+1;
			}

			if(isset($n['position']))
				$node->position=$n['position'];


			if (isset($n['condition'])) {
				$node->branchs=array();
				foreach($n['condition'] as $c) {
					$x=new stdClass;
					$x->depth=$node->depth;
					$p=$this->_scan1($c['location'], $x);
					if (isset($c['literal']))
						$p->value=$c['literal'];
					if (isset($c['operator']))
						$p->operator=$c['operator'];
					if (isset($c['logical']))
						$p->logical=$c['logical'];
					$node->branchs[]=$x->trunk;
				}
			}
			if (!is_null($previous)) {
				$previous->trunk=$node;
			}
			$previous=$node;
		}
		return $node;
	}
	private function _scan2(array $a){
		 $loc='';
		 foreach($a as $n) {
			 $loc .= $n['axis'].'::';
			 $loc .= $n['localName'];
			 if (isset($n['position'])) {
				 $loc .= '['.$n['position'].']';
			 }

			 if (isset($n['condition'])) {
				 $loc .= '[';
				 $ope=null;
				 foreach($n['condition'] as $k => $c) {
					 if ($k > 0 and isset($c['logical'])) {
						 $loc .= ' '.$c['logical'].' ';
					 }
					 if ($k == 0 and isset($c['logical'])) {
						 $ope=$c['logical'];
					 }
					 else if ($k > 0 and is_null($ope)) {
						 $loc .= ' and ';
					 }
					 else if ($k > 0 and !is_null($ope)) {
						 $loc .= ' '.$ope.' ';
						 $ope=null;
					 }

					 $loc .= $this->_scan2($c['location']);
					 if (isset($c['operator']))
						 $loc .=  ' '.$c['operator'].' ';
					 if (isset($c['literal']))
						 $loc .=  '\''.addcslashes($c['literal'], "'").'\'';
				 }
				 $loc .= ']';
			 }
			 $loc .= '/';
		}
		return rtrim($loc, '/');
	}
	protected function location(array &$ret){
		$ret['location']=array();
		$i=0;
		do {
			$ret['location'][$i]=array();
			$ctrl=$this->axis($ret['location'][$i]);
			if (is_null($ctrl)) {
				unset($ret['location'][$i]);
				break;
			}

			do {
				$c1=$this->position($ret['location'][$i]);
				$c2=$this->condition($ret['location'][$i]);
				if (is_null($c1) and is_null($c2)) break;
			} while(1);

			++$i;
		} while (1);
		if ($i === 0) return null;
		else return true;
	}
	protected function axis(array &$ret){
		if (preg_match(',^\s*[/]?(ancestor::|ancestor-or-self::|descendant::|descendant-or-self::|child::|attribute::|parent::|self::|following::|following-sibling::|namespace::|precesing::|preceding-sibling::)([\w\(\):{1}]+),i', $this->currentString, $m)) {
			$this->forward(strlen($m[0]));
			$ret['axis']=trim($m[1], ':/');
			$ret['localName']=$m[2];

			return true;
		}
		else if(preg_match(',^\s*(//|[/]?@|\.\./|\./|/)([\w:{1}]+),i', $this->currentString, $m)) {
			$this->forward(strlen($m[0]));
			$ret['axis']=strtr($m[1],[
				'/@'=>'attribute',
				'@'=>'attribute',
				'./'=>'self',
				'//'=>'descendant-or-self',
				'/'=>'child'
				]
			);

			$ret['localName']=$m[2];
			return true;
		}
		else if(preg_match(',^\s*[/]?(@\*|\*|\.\.|\./|/),i', $this->currentString, $m)) {
			$this->forward(strlen($m[0]));
			$ret['axis']=strtr($m[1], 
				[
					'@*'=>'attribute-or-element',
					'..'=>'parent',
					'*'=>'child'
				]
			);
			$ret['localName']='';
			return true;
		}
		else if(preg_match(',^\s*[/]?([\w:{1}]+),i', $this->currentString, $m)) {
			$this->forward(strlen($m[0]));
			$ret['axis']='child';
			$ret['localName']=$m[1];
			return true;
		}
		else{
			return null;
		}
	}
	protected function position(array &$ret){
		if (preg_match(',^\s*\[(position\(\)\s*=\s*)?\s*([0-9]+)\s*\],',$this->currentString, $m)) {
			if($m[2]){
				$this->forward(strlen($m[0]));
				$ret['position']=$m[2];
				return true;
			}
		}
		return null;
	}
	protected function parenthesis(array &$ret,&$i){
		if (preg_match(',^\s*((\w+)?\(|\))\s*,i', $this->currentString, $m)) {
			if(isset($m[2])){
				$ret[++$i]=['function'=>$m[2],
				'parenthesis'=>'('];
			}
			else{
				$ret[++$i]=['parenthesis'=>$m[1]];
			}
			$this->forward(strlen($m[0]));
			return true;
		}
		return false;
	}
	protected function condition(array &$ret){
		if (preg_match(',^\s*\[\s*,',$this->currentString, $m)) {
			$this->forward(strlen($m[0]));

			if (!isset($ret['condition'])) {
				$ret['condition']=array();
				$i=0;
			}
			else {
				$i=count($ret['condition']);
			}
			do {
				$ret['condition'][$i]=array();
				
				while($this->parenthesis($ret['condition'],$i)){}
				
				$ctrl=$this->location($ret['condition'][$i]);
				if (is_null($ctrl)) {
					unset($ret['condition'][$i]);
					break;
				}
				
				while($this->parenthesis($ret['condition'],$i)){}

				$ctrl=$this->operator($ret['condition'][$i]);
				if (is_null($ctrl)) break;
				
				while($this->parenthesis($ret['condition'],$i)){}
				
				$ctrl=$this->literal($ret['condition'][$i]);
				if (is_null($ctrl)) break;
				
				while($this->parenthesis($ret['condition'],$i)){}
				
				$ctrl=$this->logical($ret['condition'][$i]);
				if (is_null($ctrl)) break;
				
				while($this->parenthesis($ret['condition'],$i)){}
				
				$i++;
			} while(1);

			if (preg_match(',^\s*\]\s*,',$this->currentString, $m)) {
				$this->forward(strlen($m[0]));
				return true;
			}
			else return null;
		}
		else return null;
	}
	protected function operator(array &$ret){
		if (preg_match(',^\s*(<=|<|>=|=|!=)\s*,',$this->currentString, $m)) {
			$this->forward(strlen($m[0]));
			$ret['operator']=$m[1];
			return true;
		}
		else return null;
	}
	protected function literal(array &$ret){
		if (preg_match(',^\s*([0-9]+)\s*,',$this->currentString, $m)) {
			$this->forward(strlen($m[0]));
			$ret['literal']=$m[1];
			return true;
		}
		else if (preg_match("{^\s*[\"]([^\"\\\\]*(?:\\\\.[^\"\\\\]*)*)[\"]}x",$this->currentString, $m)) {
			$this->forward(strlen($m[0]));
			$ret['literal']=stripslashes($m[1]);
			return true;
		}
		else if (preg_match('{ ^\s*[\']([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)[\']}x',$this->currentString, $m)) {
			$this->forward(strlen($m[0]));
			$ret['literal']=stripslashes($m[1]);
			return true;
		}
		else return null;
	}
	protected function logical(array &$ret){
		if (preg_match(',^\s*(and|or)\s*,',$this->currentString, $m)) {
			$this->forward(strlen($m[0]));
			if ($m[1] == 'or')
				$ret['logical']=$m[1];
			return true;
		}
		else return null;
	}
}


?>
