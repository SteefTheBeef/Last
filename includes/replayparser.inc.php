<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**¤
 * ReplayParser - Parse in-line data for TrackMania replays
 * Created by Xymph <tmn@gamers.org>
 * If your replay data is in a file, use the GBXReplayFetcher class
 * in the GBXDataFetcher module instead.
 *
 * v1.0: Initial release
 */

class ReplayParser {

	public $uid, $version, $author, $envir, $nickname, $login, $replay,
	       $xml, $parsedxml, $xmlver, $exever, $respawns, $stuntscore,
	       $validable, $cpscur, $cpslap;

	private $replaydata, $ptr;

	/**
	 * Fetches a hell of a lot of data about a replay
	 *
	 * @param Base64 $replaydata
	 *        The replay data
	 * @return ReplayParser
	 *        If $uid is empty, replay data couldn't be extracted
	 */
  function __construct($replaydata) {

		$this->replaydata = $replaydata;
		$this->ptr = 0;
   try {
			if ($this->getData() === false) {
				$this->uid = null;
			}
		} catch (UnexpectedValueException $exception) {
			$this->uid = null;
			$this->parsedxml = array();
		}
		$this->replaydata = '';  // for print_r
	}  // ReplayParser

	// data read function
	private function ReadData($len) {

   if ($len < 0 || $len > strlen($this->replaydata) - $this->ptr) {
			throw new UnexpectedValueException('Truncated replay data');
		}
		$data = substr($this->replaydata, $this->ptr, $len);
		$this->ptr += $len;
		return $data;
	}  // ReadData

	// string read function
	private function ReadString() {

		$data = $this->ReadData(4);
		$result = unpack('Vlen', $data);
		$len = $result['len'];
		if ($len <= 0 || $len >= 0x10000) {  // for large XML blocks
      throw new UnexpectedValueException('Invalid replay string length');
		}
		$data = $this->ReadData($len);
		return $data;
	}  // ReadString

	// parser functions
	private function startTag($parser, $name, $attribs) {
		// echo 'startTag: ' . $name . "\n"; print_r($attribs);
		if ($name == 'DEPS') {
			$this->parsedxml['DEPS'] = array();
		} elseif ($name == 'DEP') {
			$this->parsedxml['DEPS'][] = $attribs;
		} else {  // HEADER, IDENT, DESC, TIMES
			$this->parsedxml[$name] = $attribs;
		}
	}  // startTag

	private function charData($parser, $data) {
		// nothing to do here
		// echo 'charData: ' . $data . "\n";
	}  // charData

	private function endTag($parser, $name) {
		// nothing to do here
		// echo 'endTag: ' . $name . "\n";
	}  // endTag

	private function getData() {

		// check for minimal data
		if (!isset($this->replaydata) || !is_string($this->replaydata) || strlen($this->replaydata) < 5) {
			return false;
		}

		// check for magic GBX header
		$data = $this->ReadData(5);
		if ($data != 'GBX' . chr(6) . chr(0)) {
			return false;
		}

		$skip = $this->ReadData(4);   // "BUCR" | "BUCE"
		// get GBX type & check for Replay
		$data = $this->ReadData(4);
		$r = unpack('Ngbxtype', $data);
		$t = sprintf('%08X', $r['gbxtype']);
		if ($t != '00E00724' && $t != '00F00324' && $t != '00300903') {
			return false;
		}

		// get GBX version: 1 = TM, 2 = TMPU/TMO/TMS/TMN/TMU/TMF
		$skip = $this->ReadData(4);  // data block offset
		$data = $this->ReadData(4);
		$r = unpack('Vversion', $data);
		$this->version = $r['version'];
		// check for unsupported versions
		if ($this->version < 1 || $this->version > 2) {
			return false;
		}

		// get Index (marker/lengths) table
		for ($i = 1; $i <= $this->version; $i++) {
			$data = $this->ReadData(8);
			$r = unpack('Nmark'.$i . '/Vlen'.$i, $data);
			$len[$i] = $r['len'.$i];
		}
		if ($this->version == 2) {  // clear high-bit
			$len[2] &= 0x7FFFFFFF;
		}

		// start of Strings block:
		// 0x1D (TM v1), 0x25 (all v2)
		// check type of Strings block
		$data = $this->ReadData(4);
		$r = unpack('Vstrtype', $data);
		$strtype = $r['strtype'];

		if ($strtype >= 4) {
			$skip = $this->ReadData(8);  // 03 00 00 00 and 00 00 00 80
			$this->uid = $this->ReadString();
			$skip = $this->ReadData(4);  // 00 00 00 40
			$this->envir = $this->ReadString();
			$skip = $this->ReadData(4);  // 00 00 00 [40|80]
			$this->author = $this->ReadString();
			$data = $this->ReadData(4);
			$r = unpack('Vreplay', $data);
			$this->replay = $r['replay'];
			$this->nickname = $this->ReadString();

			// check whether to get login (TMU/TMF, exever>="0.1.9.0")
			if ($strtype >= 6) {
				$this->login = $this->ReadString();
			}
		}

		// get optional XML block & wrap lines for readability
		if ($this->version >= 2) {
			$this->xml = $this->ReadString();
			$this->xml = str_replace("><", ">\n<", $this->xml);
		}

		// parse XML block too?
		$this->parsedxml = array();
		if ($this->xml) {
			// define a dedicated parser to handle the attributes
			$xml_parser = xml_parser_create();
     xml_set_element_handler($xml_parser, array($this, 'startTag'), array($this, 'endTag'));
			xml_set_character_data_handler($xml_parser, array($this, 'charData'));

			// escape '&' characters
     $this->xml = preg_replace('/&(?!#\d+;|#x[0-9a-fA-F]+;|amp;|lt;|gt;|quot;|apos;)/', '&amp;', $this->xml);
			$xml = mb_check_encoding($this->xml, 'UTF-8')
				? $this->xml : mb_convert_encoding($this->xml, 'UTF-8', 'ISO-8859-1');

     if (!xml_parse($xml_parser, $xml, true)) {
				$this->parsedxml = array();
				return false;
			}
     unset($xml_parser);

			// extract some specific attributes that aren't in the Header block
      $this->xmlver = $this->parsedxml['HEADER']['VERSION'] ?? null;
			$this->exever = $this->parsedxml['HEADER']['EXEVER'] ?? null;
			$this->respawns = $this->parsedxml['TIMES']['RESPAWNS'] ?? null;
			$this->stuntscore = $this->parsedxml['TIMES']['STUNTSCORE'] ?? null;
			$this->validable = $this->parsedxml['TIMES']['VALIDABLE'] ?? null;
			if (isset($this->parsedxml['CHECKPOINTS'])) {
       $this->cpscur = $this->parsedxml['CHECKPOINTS']['CUR'] ?? null;
				$this->cpslap = $this->parsedxml['CHECKPOINTS']['ONELAP'] ?? null;
			}
		}
	}  // getData
}  // class ReplayParser
?>
