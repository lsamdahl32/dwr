<?php
/**
 * MyPDF Class
 * Creates PDF files
 * Extends tFPDF which is a fork of FPDF to support UTF-8 fonts
 *
 * based on Reports_class.php
 *
 * @author Lee Samdahl
 * @date 11/24/2021
 */

require(PLMPATH . 'classes/tfpdf/tfpdf.php'); // todo note beta

class MyPDF extends tFPDF
{
    private string $report_title = '';
    private string $orientation = 'P';
    private string $company_name = '';
    private string $paper = 'letter';
    private bool $no_date = false;
    private string $selection_criteria = '';
    private float $margins = .5;
	private float $width = 0;
    private array $columns = array();
    private int $column_cnt = 0;
    private string $report_date = '';
    private bool $no_col_hdr = false;
    private bool $statement = false;
    private bool $no_footer = false;
    private float $scaleFactor = 1.0;
    private float $line_height;
	private bool $center = false;
    private int $l_level = 0;
    private array $l_type = array();
    private array $ol_count = array();
    private ?string $HREF = '';

    /**
     * @param string $report_title
     * @param string $co_name
     * @param float $scaleFactor
     */
    public function __construct(string $report_title, string $co_name, float $scaleFactor = 1.0)
    {
        parent::__construct();

        $this->report_title = $report_title;
        $this->company_name = $co_name;
        $this->scaleFactor = $scaleFactor;
        $this->line_height = .2 * $this->scaleFactor; // default
    }

    /**
     * @param string $orientation
     * @param string $paper
     * @param float $margins
     * @param bool $no_clm_hdr
     * @param bool $isStatement
     * @param bool $no_footr
     */
    public function init(string $orientation, string $paper, float $margins, bool $no_clm_hdr = false, bool $isStatement = false, bool $no_footr = false)
	{
        $this->orientation = $orientation;
        $this->paper = $paper;
        $this->margins = $margins;
		$this->no_col_hdr = $no_clm_hdr;
		$this->statement = $isStatement;
		$this->no_footer = $no_footr;
		$this->tFPDF($this->orientation, "in", $this->paper);
        $this->SetMarg($this->margins, $this->margins, $this->margins); //deft .5 in
        $this->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true); // unicode fonts
        $this->AddFont('DejaVu','B','DejaVuSansCondensed-Bold.ttf',true);
        $this->AddFont('DejaVu','I','DejaVuSansCondensed-Oblique.ttf',true);
        $this->AddFont('DejaVu','BI','DejaVuSansCondensed-BoldOblique.ttf',true);
		$this->SetTitle($this->report_title);
		$this->AddPage();
	}

	public function Header($show_first_page=false, $h_page = 0)
	{
		// if company_name is blank, skip all header printing i.e. for labels
		if (strlen($this->company_name)>0){
			if ($this->PageNo()==1 or $show_first_page) {
			    if ($this->company_name == 'Spectrasonics'){
			        $this->spectraCompanyName();
                } else {
                    //Select Arial bold 15
                    $this->SetFont('DejaVu', 'BI', (16 * $this->scaleFactor));
                    //center the line
                    $this->Cell(0, $this->line_height, $this->company_name, 0, 1, 'C');
                }
                $this->SetFont('DejaVu', 'B', (12 * $this->scaleFactor));
                $this->Cell(0, $this->line_height, $this->report_title, 0, 1, 'C');
                if (strlen($this->selection_criteria) > 0) {
                    $this->SetFont('DejaVu', '', (10 * $this->scaleFactor));
                    $this->Cell(0, $this->line_height, $this->selection_criteria, 0, 1, 'C');
                }
                $this->SetFont('DejaVu', 'B', (10 * $this->scaleFactor));
                if ($this->report_date == '') {
                    $this->report_date = date('m/d/Y');
                }
                if (!$this->no_date) {
                    if ($this->statement) {
                        $this->Ln(.4);
                        $this->Cell(0, .18, date('F j, Y', strtotime($this->report_date)), 0, 1, 'R');
                    } else {
                        $this->Cell(0, .18, date('F j, Y', strtotime($this->report_date)), 0, 1, 'L');
                    }
                }
            } elseif (!$this->statement){
			    $this->SetFont('DejaVu','B',(10 * $this->scaleFactor));
			    $this->Cell(0, $this->line_height, $this->report_title.' (Continued)', 0, 1, 'L');
			}
			if (!$this->statement){
			    //Line break
			    $this->Ln(.04);
				// horiz line
				$this->Line($this->GetX(), $this->GetY(), $this->GetX()+$this->width, $this->GetY());
			    //Line break
			    $this->Ln(.02);
			}
		    $this->SetFont('DejaVu','',(10 * $this->scaleFactor));// deft report font
		    if (!$this->no_col_hdr){
				$this->column_headings(true, $h_page);
			}
		}
	}

	public function Footer()
	{
		if (!$this->no_footer){
		    $this->SetY(-.6);
			// horiz line
			$this->Line($this->GetX(), $this->GetY(), $this->GetX()+$this->width, $this->GetY());
		    $this->Ln(.1);
		    $this->SetFont('DejaVu','',8);
		    //Page number
		    $this->Cell(0,0,'Page '.$this->PageNo(),0,0,'C');
   		}
	}

    /**
     * @param float $left
     * @param float $top
     * @param float|int $right
     */
    public function SetMarg(float $left, float $top, float $right = -1)
    {
        if ($right == -1) $right = $left;
        $this->SetMargins($left, $top, $right);
        $this->margins = $left;
        if ($this->paper == 'legal'){
            if ($this->orientation=='L'){
                $this->width = 14 - ($left + $right);
            } else {
                $this->width = 8.5 - ($left + $right);
            }
        } elseif ($this->paper == 'letter'){
            if ($this->orientation=='L'){
                $this->width = 11 - ($left + $right);
            } else {
                $this->width = 8.5 - ($left + $right);
            }
        }
    }

    /**
     * @param float $indent
     */
    public function hLine(float $indent=0)
	{
		// horiz line
		$this->Line($this->lMargin+$indent, $this->GetY(), $this->lMargin+$this->width, $this->GetY());

	}

    /**
     * @param bool $box
     * @param int $h_page
     */
    public function column_headings(bool $box = true, int $h_page = 0)
	{
		if ($this->column_cnt > 0){
			// display column headings and line
		    $this->SetFont('DejaVu', 'B', (9 * $this->scaleFactor));
		    if ($box){
		        $this->SetY($this->GetY() - .01);
                $this->SetFillColor(230);
                $this->SetDrawColor(230);
				$this->Rect($this->lMargin, $this->GetY(), $this->width, ($this->line_height * $this->scaleFactor), 'DF');
                $this->SetDrawColor(0);
			}
		    $this->SetX($this->lMargin);
		    for ($i=0; $i < $this->column_cnt; $i++){
		        if ($this->columns[$i]['show'] or !isset($this->columns[$i]['show'])) {
                    if ($this->columns[$i]['hpage'] == $h_page) {
                        If ($i > 0) { // multiline headings
                            if ($this->columns[$i - 1]['hpage'] == $h_page) {
                                If ($this->columns[$i]['Left'] < $this->columns[$i - 1]['Left']) {
                                    //Line break
                                    $this->Ln();
                                }
                            }
                        }
                        // get next column where show == 1
                        for ($j = $i + 1; $j < $this->column_cnt; $j++) {
                            if ($this->columns[$j]['show'] == 1) {
//                                echo $j .' '.$this->columns[$j]['field'].'<br/>';
                                break;
                            }
                        }
                        // make sure column headings don't overlap
                        If (($i < $this->column_cnt - 1) And ($this->columns[$j]['Left'] > $this->columns[$i]['Left']) And !$this->columns[$i]['Decimal']) {
                            $this->columns[$i]['width'] = $this->columns[$j]['Left'] - $this->columns[$i]['Left'];
//                            echo 'A '.$this->columns[$i]['field'].' '.$this->columns[$i]['width'].'<br/>';
                        } elseif ($this->columns[$i]['Decimal']) {
                            $this->columns[$i]['width'] = $this->GetStringWidth(trim($this->columns[$i]['Head']));
//                            echo 'B '.$this->columns[$i]['field'].' '.$this->columns[$i]['width'].'<br/>';
                        } elseif ($i == $this->column_cnt - 1 or $j = $this->column_cnt) { // on last column of page
                            $this->columns[$i]['width'] = $this->width - $this->columns[$i]['Left'];
                            if ($this->columns[$i]['width'] > ($this->columns[$i]['width'] * $this->scaleFactor)) $this->columns[$i]['width'] = ($this->columns[$i]['width'] * $this->scaleFactor);
//                            echo 'C '.$this->columns[$i]['field'].' '.$this->columns[$i]['width'].'<br/>';
                        } Else {
                            $this->columns[$i]['width'] = $this->width - $this->columns[$j]['Left'];
//                            echo 'D '.$this->columns[$i]['field'].' '.$this->columns[$i]['width'].'<br/>';
                        }
                        $tx = trim($this->columns[$i]['Head']);
                        While ($this->GetStringWidth($tx) > $this->columns[$i]['width']) {
                            $tx = substr($tx, 0, strlen($tx) - 1);
                        }
                        If ($this->columns[$i]['Decimal'] and !isset($this->columns[$i]['align'])) {
                            If (strlen(trim($tx)) > 3) {
                                $pos = $this->columns[$i]['Left'] + $this->GetStringWidth(trim(substr($tx, strlen(trim($tx)) - 2))); // col + width of last 2 letters and right aligned
                                $this->DecimalPrint(trim($tx), $pos);
                            } else {
                                $this->DecimalPrint(trim($tx), $this->columns[$i]['Left']);
                            }
                        } elseif (isset($this->columns[$i]['align']) and ($this->columns[$i]['align'] == 'right' or $this->columns[$i]['align'] == 'decimal')) {
                            $this->setCurrentX($this->columns[$i]['Left']);
                            $this->Cell($this->columns[$i]['width'], ($this->line_height * $this->scaleFactor), trim($tx), 0, 0, 'R');
                        } Else {
                            $this->setCurrentX($this->columns[$i]['Left']);
                            //				$this->Cell($this->columns[$i]['width'], $this->line_height, $tx, 0, 0 , 'L');
                            $this->PPrint(trim($tx) . ";", 0, $this->columns[$i]['width']);
                        }
                    }
                }
			}
//            exit;
			$this->Ln(.01);
			$this->SetFont('DejaVu', '');
            $this->PPrint();
			// horiz line
            $this->Line($this->GetX(), $this->GetY(), $this->GetX()+$this->width, $this->GetY());
    		$this->Ln(.1);
		}
	}

    /**
     * @param float $left
     * @param string $head
     * @param bool $decimal
     * @param bool $wrap
     * @param int $h_page
     * @param array $col_arry
     */
    public function column_array(float $left, string $head, bool $decimal=false, bool $wrap=false, int $h_page=0, array $col_arry = array())
    {
        $output =  array(
            'Left'      =>$left,
            'Head'      =>$head,
            'Decimal'   =>$decimal,
            'Wrap'      =>$wrap,
            'hpage'     =>$h_page,
        );
        $this->columns[] = array_merge($output, $col_arry);
        $this->column_cnt = count($this->columns);
    }

    /**
     * @param string $tx
     * @param float $col
     */
    public function DecimalPrint(string $tx, float $col)
	{
    	// align decimal in printout with col
		$d = strpos($tx, ".");
		If ($d > 0){
			$this->setCurrentX($col - $this->GetStringWidth(substr($tx, 0, $d)));
		} Else { // no decimal prints as if right aligned
			$this->setCurrentX($col - $this->GetStringWidth($tx));
		}
		$this->PPrint($tx . ";");
	}

    /**
     * @param string|null $tx
     * @param int|null $col
     * @param float|int|null $wid
     * @param bool|null $ignore_semicolon
     */
    public function PPrint(?string $tx='', ?int $col=0, ?float $wid=0, ?bool $ignore_semicolon = false)
	{
		// output $tx optionally indent by $col
		// print to window or printer with optional tab
		// append a ";" to prevent cr - if $ignore_semicolon is true then semicolon is passed thru
		if ($wid==0){
			$wid=$this->GetStringWidth(trim($tx));
		}
		If ($col > 0){
			$this->setCurrentX($col);
		}
		If (substr($tx, strlen($tx)- 1, 1) == ";" and $ignore_semicolon==false){
			$s = substr($tx, 0, strlen($tx) - 1);
			$this->Cell($wid,($this->line_height * $this->scaleFactor),$s);
		} Else {
			$this->Cell($wid,($this->line_height * $this->scaleFactor),$tx,0,1);
		}

	}

    /**
     * @param string $tx
     * @param int|null $ln
     */
    public function PrintCenter(string $tx, ?int $ln=1)
	{
	    // center on full line
		$this->SetX($this->lMargin);
		$this->Cell($this->width,($this->line_height * $this->scaleFactor),$tx,0,$ln,'C');
	}

    /**
     * @param string $tx
     * @param int|null $ln
     */
	public function PrintRightAlign(string $tx, ?int $ln=1)
	{
	    // right of full line
		$this->SetX($this->lMargin);
		$this->Cell($this->width,($this->line_height * $this->scaleFactor),$tx,0,$ln,'R');
	}

    /**
     * @param float $x
     */
    public function setCurrentX(float $x)
	{
		$this->SetX($x + $this->lMargin);
	}

    /**
     * @param float $y
     */
    public function setCurrentY(float $y)
	{
		$this->SetY($y + $this->tMargin);
	}

    /**
     * @param string $s
     * @param int $cnum
     * @param int $LnCnt
     */
    public function PrintColumns(string $s, int $cnum, &$LnCnt = 0)
	{
		// print a single column's data using Clm structure
		// s is data, cnum is the current column
		// LnCnt returns number of extra lines printed if .Wrap=true
        $tx = $s;
        if ($this->columns[$cnum]['Decimal']){
			// right align
			$this->DecimalPrint($tx, $this->columns[$cnum]['Left']);
			If ($cnum == $this->column_cnt){
				$this->PPrint();
			}
		} Else {
            $this->setCurrentX($this->columns[$cnum]['Left']);
            $LnCn = 1;
            $length = $this->columns[$cnum]['width'];
            $curY = $this->GetY();
            If ($this->columns[$cnum]['Wrap']){
                $this->MultiCell($length, ($this->line_height * $this->scaleFactor), $tx,0,'L'); // change to multicell for better performance on large fields - Lee 11/17/2017
                // if not new page
                if ($curY < $this->GetY()){
                    $LnCn =  ($this->GetY() - $curY) / ($this->line_height * $this->scaleFactor);
                    // set Y back to original line
                	$this->setY($curY);
               	}
            } Else {
                If (!is_null($tx)){
                    $this->setY($curY + .01); // work-around to fix covering of subtotal lines 4/2/19
                    $this->setCurrentX($this->columns[$cnum]['Left']);
                    $height = (($this->line_height * $this->scaleFactor) - .01);
                    if ($this->columns[$cnum]['format'] == 'center' or (isset($this->columns[$cnum]['align']) and $this->columns[$cnum]['align'] == 'center')) { // added centering - (Lee) 9/7/2017
                        $this->Cell($length, $height, $tx, 0, 0, 'C', false); // always fill 4/2/19
                    } elseif (isset($this->columns[$cnum]['align']) and ($this->columns[$cnum]['align'] == 'right' or $this->columns[$cnum]['align'] == 'decimal')) { // added align parameter 4/2/19
                        $this->Cell($length, $height, $tx, 0, 0, 'R', false);
                    } else {
                        // use Wrap function to trim the line to fit column
                        $this->Cell($length, $height, $this->Wrap($tx, $length), 0, 0, 'L', false);
//                        $this->PPrint($this->Wrap($tx, $length) . ";");
                    }
                    $this->setY($curY); // undo work-around
                }
            }
            If ($cnum == $this->column_cnt){
                $this->PPrint();
            }
            if ($LnCn > $LnCnt){
            	// only send back the line count if larger than existing
            	$LnCnt = $LnCn;
            }
        }
	}

    /**
     * Returns the count of wrapped lines without rendering
     * Author: Ron Korving
     * License: FPDF
     * http://www.fpdf.org/en/script/script49.php
     *
     * @param $text
     * @param float $maxwidth
     * @return int
     */
    private function WordWrap(&$text, float $maxwidth): int
    {
        $text = trim($text);
        if ($text==='')
            return 0;
        $space = $this->GetStringWidth(' ');
        $lines = explode('\n', $text);
        $text = '';
        $count = 0;
        foreach ($lines as $line) {
//            echo $line.'<br>';
            $words = preg_split('/ +/', $line);
            $width = 0;
            foreach ($words as $word) {
                $wordwidth = $this->GetStringWidth($word);
                if ($wordwidth > $maxwidth) {
                    // Word is too long, we cut it
                    for($i=0; $i<strlen($word); $i++) {
                        $wordwidth = $this->GetStringWidth(substr($word, $i, 1));
                        if($width + $wordwidth <= $maxwidth) {
                            $width += $wordwidth;
                            $text .= substr($word, $i, 1);
                        } else {
                            $width = $wordwidth;
                            $text = rtrim($text)."\n".substr($word, $i, 1);
                            $count++;
                        }
                    }
                } elseif($this->width + $wordwidth <= $maxwidth) {
                    $width += $wordwidth + $space;
                    $text .= $word.' ';
                } else {
                    $width = $wordwidth + $space;
                    $text = rtrim($text)."\n".$word.' ';
                    $count++;
                }
            }
            $text = rtrim($text)."\n";
            $count++;
        }
        $text = rtrim($text);
        return $count;
    }

    /**
     * @param $ln
     * @param float $length
     * @param bool $AllowNewLineChars
     * @return string
     */
    public Function Wrap(&$ln, float $length, bool $AllowNewLineChars = False):string
	{
		// ln is text, length is desired length in current scalemode
		$str1 = $ln;
		$str2 = '';
		If (!$AllowNewLineChars){
			$str1 = str_replace(Chr(13), " ", $str1); // remove cr and lf
			$str1 = str_replace(Chr(10), " ", $str1);
		}
		$place = strlen($ln);
		$tw = $this->GetStringWidth($str1);
		While ($tw > $length){
			If (strpos($str1, " ") == 0){
				break; // no spaces - go to truncate routine below
			}
			For ($place = strlen($str1); $place > 1; $place--){
				$str2 = substr($str1, $place - 1, 1) . $str2;
				$str1 = substr($str1, 0, $place - 1);
				if (substr($str1, $place-2, 1) == " "){
 					break;
 				}
			}
 			$tw = $this->GetStringWidth($str1);
 		}
		If ($tw > $length){ // if no spaces and still too long
			// remove one char at a time from right until tw<=length
			For ($place = strlen($str1); $place > 1; $place--){
				$str2 = substr($str1, $place - 1, 1) . $str2;
				$str1 = substr($str1, 0, $place - 1);
				$tw = $this->GetStringWidth($str1);
				If ($tw <= $length){
					break;
				}
			}
		}
		If (strpos($str1, Chr(10)) > 0){
			//Check for cr in line and break it
			$tw = $str1;
			$str1 = substr($tw, 0, strpos($tw, Chr(10)));
			$str2 = substr($tw, strpos($tw, Chr(10)) + 1) . $str2;
		}
		$ln = trim($str2);
		return trim($str1);
	}

    /**
     * @param array $row
     * @param float $lineHeight
     * @return float
     */
    public function getRowHeight(array $row, float $lineHeight):float
    {
        // loop through cols check for any wrapped fields return longest field
        $largest = 1;
        for ($i=0; $i<count($this->columns); $i++) {
            if ($this->columns[$i]['Wrap']){
                $tx = $row[$this->columns[$i]['field']];
                $LnCn = $this->WordWrap($tx, ($this->columns[$i]['width']) * $this->scaleFactor);
                if ($LnCn > $largest){
                    $largest = $LnCn;
                }
            }
        }
        if ($largest > 10) $largest = 10; // can't allow too many lines!
        if ($largest > 1) $largest = $largest + .3; // needs a fudge factor
        return $largest * $lineHeight;
    }

    /**
     * @return float
     */
    public function bMarg():float
	{
		return $this->bMargin;
	}

    /**
     * @return float
     */
    public function getWidth():float
	{
		return $this->width;
	}

    /**
     * @param float $h
     * @param string $txt
     * @param string $link
     * @param float $subFontSize
     * @param float $subOffset
     */
    public function subWrite(float $h, string $txt, string $link='', float $subFontSize=12, float $subOffset=0)
    {
        // resize font
        $subFontSizeold = $this->FontSizePt;
        $this->SetFontSize($subFontSize);

        // reposition y
        $subOffset = ((($subFontSize - $subFontSizeold) / $this->k) * 0.3) + ($subOffset / $this->k);
        $subX        = $this->x;
        $subY        = $this->y;
        $this->SetXY($subX, $subY - $subOffset);

        //Output text
        $this->Write($h, $txt, $link);

        // restore y position
        $subX        = $this->x;
        $subY        = $this->y;
        $this->SetXY($subX,  $subY + $subOffset);

        // restore font size
        $this->SetFontSize($subFontSizeold);
    }

    /**
     * @param string $html
     * @param array $data
     * @param float $fontsize
     */
    public function WriteHTML(string $html, array $data = array(), float $fontsize = 10)
    {
        // HTML parser
        $html = str_replace("\n",' ', $html);
        $html = str_replace("&nbsp;",' ', $html);
        $a = preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
        foreach($a as $i=>$e)
        {
            if($i%2==0)
            {
                if (strpos($e, '{{') !== false){
                    // look for field reference
                    $fld = substr($e, strpos($e, '{{')+2, strpos($e, '}}')-2);
                    $e = str_replace('{{'.$fld.'}}', $data[$fld], $e);
                }
                // Text
                if($this->HREF) {
                    $this->PutLink($this->HREF, $e);
                } else {
                    if ($this->center) {
                        $this->PrintCenter($e);
                    } else {
                        $this->Write($this->line_height, ltrim($e));
                    }
                }
            } else {
                // Tag
                if($e[0]=='/')
                    $this->CloseTag(strtoupper(substr($e,1)), $fontsize);
                else
                {
                    // Extract attributes
                    $a2 = explode(' ',$e);
                    $tag = strtoupper(array_shift($a2));
                    $attr = array();
                    foreach($a2 as $v)
                    {
                        if(preg_match('/([^=]*)=["\']?([^"\']*)/',$v,$a3))
                            $attr[strtoupper($a3[1])] = $a3[2];
                    }
                    $this->OpenTag($tag, $attr, $fontsize);
                }
            }
        }
    }

    /**
     * @param string $tag
     * @param array $attr
     * @param float $fontsize
     */
    private function OpenTag(string $tag, array $attr, float $fontsize = 10)
    {
        // Opening tag
        switch ($tag) {
            case 'B':
            case 'I':
            case 'U':
                $this->SetStyle($tag,true);
                break;
            case 'STRONG':
                $this->SetStyle('B',true);
                break;
            case 'A':
                $this->HREF = $attr['HREF'];
                break;
            case 'BR':
                $this->Ln($this->line_height);
                break;
            case 'CENTER':
                $this->center = true;
                break;
            case 'H2':
                $this->SetFont('DejaVu', 'B', ($fontsize * 1.2));
                $this->setY($this->getY()+.01);
                break;
            case 'H3':
                $this->SetFont('DejaVu', 'B', ($fontsize * 1.1));
                $this->setY($this->getY()+.01);
                break;
            case 'LI':
                $this->setX($this->lMargin-.17);
                if ($this->l_type[$this->l_level] == 'O') {
                    $this->ol_count[$this->l_level]++;
                    $this->PPrint($this->ol_count[$this->l_level].'. ;');
                } else {
                    if ($this->l_level % 2 == 0) {
                        $this->PPrint('° ;');
                    } else {
                        $this->PPrint('• ;');
                    }
                }
                $this->setX($this->lMargin);
                break;
            case 'OL':
                $this->l_level = $this->l_level + 1;
                $this->l_type[$this->l_level] = 'O';
                $this->ol_count[$this->l_level] = 0;
                $this->SetMarg(1 + (.35 * $this->l_level), 1);
                $this->PPrint();
                break;
            case 'UL':
                $this->l_level = $this->l_level + 1;
                $this->l_type[$this->l_level] = 'U';
                $this->SetMarg(1 + (.35 * $this->l_level), 1);
                $this->PPrint();
                break;
            case 'P':
                $this->PPrint();
                break;
        }
    }

    /**
     * @param string $tag
     * @param float $fontsize
     */
    private function CloseTag(string $tag, float $fontsize = 10)
    {
        // Closing tag
        switch ($tag) {
            case 'B':
            case 'I':
            case 'U':
                $this->SetStyle($tag,false);
                break;
            case 'STRONG':
                $this->SetStyle('B',false);
                break;
            case 'A':
                $this->HREF = '';
                break;
            case 'CENTER':
                $this->center = false;
                break;
            case 'H2':
                $this->SetFont('DejaVu', '', $fontsize);
                $this->PPrint();
                $this->setY($this->getY()+.02);
                break;
            case 'H3':
                $this->SetFont('DejaVu', '', $fontsize);
                $this->PPrint();
                $this->setY($this->getY()+.01);
                break;
            case 'LI':
            case 'P':
                $this->PPrint();
                break;
            case 'OL':
            case 'UL':
                $this->l_level = $this->l_level- 1;
                $this->SetMarg(1 + (.35 * $this->l_level), 1);
                $this->PPrint();
                break;
        }
    }

    /**
     * @param string $tag
     * @param int $enable
     */
    private function SetStyle(string $tag, int $enable)
    {
        // Modify style and select corresponding font
        $this->$tag += ($enable ? 1 : -1);
        $style = '';
        foreach(array('B', 'I', 'U') as $s)
        {
            if($this->$s > 0)
                $style .= $s;
        }
        $this->SetFont('',$style);
    }

    /**
     * @param string $URL
     * @param string $txt
     */
    private function PutLink(string $URL, string $txt)
    {
        // Put a hyperlink
        $this->SetTextColor(0,0,255);
        $this->SetStyle('U',true);
        $this->Write($this->line_height,$txt,$URL);
        $this->SetStyle('U',false);
        $this->SetTextColor(0);
    }

    /**
     * Format EDU License Title line with horiz scaling and character spacing
     * Expects font to be set to PeignotLTStd-Demi
     *
     * @param float $w
     * @param float $h
     * @param string $txt
     * @param int $border
     * @param int $ln
     * @param string $align
     * @param bool $fill
     * @param string $link
     */
    public function spectraCell(float $w, float $h=0, string $txt=',', int $border=0, int $ln=0, string $align='', bool $fill = false, string $link='')
    {
//        // set horizontal scaling
//        $ratio = .7;
//        $horiz_scale=$ratio*100.0;
//        $this->_out(sprintf('BT %.2F Tz ET', $horiz_scale));
//
//        //Character spacing in points
//        $char_space= 3;
//        //Set character spacing
//        $this->_out(sprintf('BT %.2F Tc ET', $char_space));

        //Pass on to Cell method
        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);

//        //Reset character spacing/horizontal scaling
//        $this->_out('BT 100 Tz ET');
//        $this->_out( 'BT 0 Tc ET');
    }

    /**
     * Return text width with scaling and extra spacing
     * May need to adjust by 87% depending on font size
     *
     * @param string $txt
     * @return float
     */
    public function getSpectraCellWidth(string $txt):float
    {
        // set horizontal scaling
//        $ratio = .7;
//        $horiz_scale=$ratio*100.0;
//        $this->_out(sprintf('BT %.2F Tz ET', $horiz_scale));

        //Character spacing in points
//        $char_space= 3;
//        //Set character spacing
//        $this->_out(sprintf('BT %.2F Tc ET', $char_space));

        //get width
        $wid = $this->GetStringWidth($txt);

        //Reset character spacing/horizontal scaling
//        $this->_out('BT 100 Tz ET');
//        $this->_out( 'BT 0 Tc ET');

        return $wid;
    }

    /**
     * Cell with horizontal scaling if text is too wide
     *
     * @param float $w
     * @param float $h
     * @param string $txt
     * @param int $border
     * @param int $ln
     * @param string $align
     * @param bool $fill
     * @param string $link
     * @param bool $scale
     * @param bool $force
     */
    private function CellFit(float $w, float $h=0, string $txt='', int $border=0, int $ln=0, string $align='', bool $fill = false, string $link='', bool $scale=false, bool $force=true)
    {
        //Get string width
        $str_width=$this->GetStringWidth($txt);

        //Calculate ratio to fit cell
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $ratio = ($w-$this->cMargin*2)/$str_width;

        $fit = ($ratio < 1 || ($ratio > 1 && $force));
        if ($fit)
        {
            if ($scale)
            {
                //Calculate horizontal scaling
                $horiz_scale=$ratio*100.0;
                //Set horizontal scaling
                $this->_out(sprintf('BT %.2F Tz ET', $horiz_scale));
            }
            else
            {
                //Calculate character spacing in points
                $char_space=($w-$this->cMargin*2-$str_width)/max($this->MBGetStringLength($txt)-1, 1)*$this->k;
                //Set character spacing
                $this->_out(sprintf('BT %.2F Tc ET', $char_space));
            }
            //Override user alignment (since text will fill up cell)
            $align='';
        }

        //Pass on to Cell method
        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);

        //Reset character spacing/horizontal scaling
        if ($fit)
            $this->_out('BT '.($scale ? '100 Tz' : '0 Tc').' ET');
    }

    /**
     * Cell with horizontal scaling only if necessary
     *
     * @param float $w
     * @param float $h
     * @param string $txt
     * @param int $border
     * @param int $ln
     * @param string $align
     * @param bool $fill
     * @param string $link
     */
    public function CellFitScale(float $w, float $h=0, string $txt='', int $border=0, int $ln=0, string $align='', bool $fill = false, string $link='')
    {
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, true, false);
    }

    /**
     * Cell with horizontal scaling always
     *
     * @param float $w
     * @param float $h
     * @param string $txt
     * @param int $border
     * @param int $ln
     * @param string $align
     * @param bool $fill
     * @param string $link
     */
    public function CellFitScaleForce(float $w, float $h=0, string $txt='', int $border=0, int $ln=0, string $align='', bool $fill = false, string $link='')
    {
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, true, true);
    }

    /**
     * Cell with character spacing only if necessary
     *
     * @param float $w
     * @param float $h
     * @param string $txt
     * @param int $border
     * @param int $ln
     * @param string $align
     * @param bool $fill
     * @param string $link
     */
    public function CellFitSpace(float $w, float $h=0, string $txt='', int $border=0, int $ln=0, string $align='', bool $fill = false, string $link='')
    {
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, false, false);
    }

    /**
     * Cell with character spacing always
     *
     * @param float $w
     * @param float $h
     * @param string $txt
     * @param int $border
     * @param int $ln
     * @param string $align
     * @param bool $fill
     * @param string $link
     */
    public function CellFitSpaceForce(float $w, float $h=0, string $txt='', int $border=0, int $ln=0, string $align='', bool $fill = false, string $link='')
    {
        //Same as calling CellFit directly
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, false, true);
    }

    /**
     * Patch to also work with CJK double-byte text
     * @param string $s
     * @return float
     */
    private function MBGetStringLength(string $s):float
    {
        if($this->CurrentFont['type']=='Type0')
        {
            $len = 0;
            $nbbytes = strlen($s);
            for ($i = 0; $i < $nbbytes; $i++)
            {
                if (ord($s[$i])<128)
                    $len++;
                else
                {
                    $len++;
                    $i++;
                }
            }
            return $len;
        }
        else
            return strlen($s);
    }

    public function pdfSpectraTitle()
    {
        $this->SetFont('Peignot', '', 32);
        $x = $this->GetX();
        $this->SetY($this->GetY()-.05);
        $this->SetX($x);
        $this->spectraCell(.24, .5, 'S', 0, 0, 'L', false, '');
        $x = $this->GetX();
        $this->SetY($this->GetY()+.05);
        $this->SetX($x);
        $this->SetFont('Peignot', '', 21.09);
        $this->spectraCell(1.9, .5, 'PECTRASONICS', 0, 0, 'L', false, '');
        $this->subWrite(.2, chr(0x00AE), '', 12, 3);
    }

    public function spectraCompanyName()
    {
        $this->AddFont('Peignot', '', 'PeignotLTStd-Demi.php');
        $this->SetFont('Peignot', '', 21.09);
        $wid = ($this->getSpectraCellWidth(strtoupper('SPECTRASONICS'.chr(0x00AE))) );
        $this->SetX((($this->getWidth()+1)/2)-($wid/2));
        $this->pdfSpectraTitle($this);
        $this->SetY($this->GetY()+.4);
    }

    /**
     * make MultiCell behave like Cell
     *
     * @param float $w
     * @param float $h
     * @param string $txt
     * @param int $border
     * @param int $ln
     * @param string $align
     * @param bool $fill
     * @return int
     */
    public function mCell(float $w, float $h=0, string $txt='', int $border=0, int $ln=0, string $align='', bool $fill=false):int
    {
        if ($ln == 0) {
            $y = $this->GetY();
            $x = $this->GetX();
            $lines = $this->MultiCell($w, $h, $txt, $border, $align, $fill);
            // position to top right of new multi line box
            $this->SetY($y);
            $this->SetX($x + $w);
        } else {
            $lines = $this->MultiCell($w, $h, $txt, $border, $align, $fill);
        }
        return $lines;
    }

    /**
     * @param bool $state
     */
    public function set_InFooter(bool $state)
    {
        $this->InFooter = $state;
    }

    /**
     * @param float $val
     */
    public function set_tMargin(float $val)
    {
        $this->tMargin = $val;
    }

    /**
     * @return float
     */
    public function get_lMargin():float
    {
        return $this->lMargin;
    }

    /**
     * @param bool $no_date
     */
    public function setNoDate(bool $no_date)
    {
        $this->no_date = $no_date;
    }

    /**
     * @param string $selection_criteria
     */
    public function setSelectionCriteria(string $selection_criteria)
    {
        $this->selection_criteria = $selection_criteria;
    }

    /**
     * @param string $report_date
     */
    public function setReportDate(string $report_date)
    {
        $this->report_date = $report_date;
    }

    /**
     * @param float $line_height
     */
    public function setLineHeight(float $line_height)
    {
        $this->line_height = $line_height * $this->scaleFactor;
    }

    /**
     * @param float $width
     */
    public function setWidth(float $width)
    {
        $this->width = $width;
    }

    /**
     * @return array
     */
    public function getColumns():array
    {
        return $this->columns;
    }

}
