<?php

namespace SPPMod\SPPView;

//require_once 'class.spphtmlelement.php';
class SPP_HTML_Element extends \SPPMod\SPPView\ViewTag
{
    public function __construct($ename)
    {
        parent::__construct('', $ename, false);
    }
}

/**
 * class SPP_HTML_TableField
 * Represents a HTML table field.
 *
 * @author Satya Prakash Shukla
 */
class SPP_HTML_TableField extends SPP_HTML_Element
{
    private $content;
    public function __construct($ename, $isheading = false)
    {
        parent::__construct($ename);
        $this->isemptyflag = false;
        if ($isheading) {
            $this->tagname = 'th';
        } else {
            $this->tagname = 'td';
        }
        $this->attrlist = ['abbr', 'align','axis','bgcolor','char','charoff','colspan','headers','height','nowrap','rowspan','scope','valign','width'];
    }

    public function setContent($cnt)
    {
        $this->content = $cnt;
    }

    public function getContent($cnt)
    {
        return $this->content;
    }

    public function getHTML(): string
    {
        if ($this->content) {
            $this->setMatterText($this->content);
        }
        return parent::getHTML();
    }
}

/**
 * Description of SPP_HTML_TableRow
 *
 * @author Administrator
 */
class SPP_HTML_TableRow extends SPP_HTML_Element
{
    private $fields;
    private $numfld = 0;
    public function __construct($ename)
    {
        parent::__construct($ename);
        $this->isemptyflag = false;
        $this->tagname = 'tr';
        $this->attrlist = ['align','bgcolor','char','charoff','valign'];
    }

    public function addField(SPP_HTML_TableField $fld)
    {
        $this->fields[$this->numfld++] = $fld;
        return true;
    }

    public function getHTML(): string
    {
        $html = $this->getStart();
        if ($this->fields) {
            foreach ($this->fields as $fld) {
                $html .= $fld->getHTML();
            }
        }
        $html .= $this->getEnd();
        return $html;
    }
}


/**
 * Description of SPP_HTML_TableSection
 *
 * @author Administrator
 */
class SPP_HTML_TableSection extends SPP_HTML_Element
{
    private $rows = [];
    private $numrows = 0;
    public function __construct($ename, $stype)
    {
        parent::__construct($ename);
        $this->isemptyflag = false;
        if ($stype == 'head') {
            $this->tagname = 'thead';
        } elseif ($stype == 'body') {
            $this->tagname = 'tbody';
        } elseif ($stype == 'foot') {
            $this->tagname = 'tfoot';
        } else {
            throw new InvalidHTMLTableSectionException('Invalid Table section: '.$stype);
        }
        $this->attrlist = ['align','char','charoff','valign'];
    }

    public function addRow(SPP_HTML_TableRow $row)
    {
        $this->rows[$this->numrows++] = $row;
        //$this->numrows+=1;
        return true;
    }

    public function getHTML(): string
    {
        $html = $this->getStart();
        if ($this->rows) {
            foreach ($this->rows as $row) {
                $html .= $row->getHTML();
            }
        }
        $html .= $this->getEnd();
        return $html;
    }
}


/**
 * Description of SPP_HTML_Table
 *
 * @author Administrator
 */
class SPP_HTML_Table extends SPP_HTML_Element
{
    private $caption;
    private $capalign = 0;
    private $headrows;
    private $bodyrows;
    private $footrows;
    public function __construct($ename)
    {
        parent::__construct($ename);
        $this->isemptyflag = false;
        $this->tagname = 'table';
        $this->attrlist = ['align','bgcolor','border','cellpadding','cellspacing','frame','rules','summary','width'];
        $this->headrows = new SPP_HTML_TableSection($ename.'head', 'head');
        $this->bodyrows = new SPP_HTML_TableSection($ename.'body', 'body');
        $this->footrows = new SPP_HTML_TableSection($ename.'foot', 'foot');
    }

    public function setCaption($cap, $calign = 'center')
    {
        $this->caption = $cap;
        $this->capalign = $calign;
    }

    public function addHeaderRow(SPP_HTML_TableRow $row)
    {
        $this->headrows->addRow($row);
    }

    public function addRow(SPP_HTML_TableRow $row)
    {
        $this->bodyrows->addRow($row);
    }

    public function addFooterRow(SPP_HTML_TableRow $row)
    {
        $this->footrows->addRow($row);
    }

    public function setHeadAttribute($attname, $attval)
    {
        $this->headrows->setAttribute($attname, $attval);
    }

    public function setBodyAttribute($attname, $attval)
    {
        $this->bodyrows->setAttribute($attname, $attval);
    }

    public function setFootAttribute($attname, $attval)
    {
        $this->footrows->setAttribute($attname, $attval);
    }

    public function getHTML(): string
    {
        $html = $this->getStart();
        if ($this->capalign != 0) {
            $html .= '<caption align="'.$this->capalign.'">'.$this->caption.'</caption>';
        }
        $html .= $this->headrows->getHTML();
        $html .= $this->bodyrows->getHTML();
        $html .= $this->footrows->getHTML();
        $html .= $this->getEnd();
        return $html;
    }
}
