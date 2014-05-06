<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2013
 * $Id$
 *
 */

require_once 'packages/OpenFlashChart/php-ofc-library/open-flash-chart.php';

/**
 * Build various graphs using Open Flash Chart library.
 *
 * This is taken from CRM_Utils_OpenFlashChart in order to build
 * a stacked bar chart
 *
 * At some point we need to reintegrate into CRM_Utils_OpenFlashChart
 * However CRM_Utils_OpenFlashChart should ideally be restructured to use OOP as
 * extension currently difficult
 */
class CRM_ReportBase_Form_Report_OpenFlashChart {
  /**
     * colours.
     * @var array
     * @static
     */

  function buildChart(&$params, $chart) {
    $openFlashChart = array();
    if ($chart && is_array($params) && ! empty($params)) {
      $chartInstance = new $chart($params);
      $chartInstance->buildChart();
      $chartObj = $chartInstance->getChart();
      $openFlashChart = array();
      if ($chartObj) {
        // calculate chart size.
        $xSize = CRM_Utils_Array::value('xSize', $params, 400);
        $ySize = CRM_Utils_Array::value('ySize', $params, 300);
        if ($chart == 'barChart') {
          $ySize = CRM_Utils_Array::value('ySize', $params, 250);
          $xSize = 60 * count($params['values']);
          //hack to show tooltip.
          if ($xSize < 200) {
            $xSize = (count($params['values']) > 1) ? 100 * count($params['values']) : 170;
          }
          elseif ($xSize > 600 && count($params['values']) > 1) {
            $xSize = (count($params['values']) + 400 / count($params['values'])) * count($params['values']);
          }
        }

        // generate unique id for this chart instance
        $uniqueId = md5(uniqid(rand(), TRUE));

        $openFlashChart["chart_{$uniqueId}"]['size'] = array(
          'xSize' => $xSize,
          'ySize' => $ySize
        );
        $openFlashChart["chart_{$uniqueId}"]['object'] = $chartObj;

        // assign chart data to template
        $template = CRM_Core_Smarty::singleton();
        $template->assign('uniqueId', $uniqueId);
        $template->assign("openFlashChartData", json_encode($openFlashChart));
      }
    }
    return $openFlashChart;
  }
}
/**
   * Base class for all non-specific actions
   *
   * @author eileen
   *
   */
class chart {
  protected $_colours = array(
    "#C3CC38",
    "#C8B935",
    "#CEA632",
    "#D3932F",
    "#D9802C",
    "#FA6900",
    "#DC9B57",
    "#F78F01",
    "#5AB56E",
    "#6F8069",
    "#C92200",
    "#EB6C5C"
  );
  protected $chartTitle;
  protected $values = array();
  protected $tooltip = array();
  protected $chart = null;
  protected $chartElement = null;
  protected $onClickFunName = null;
  protected $currencyValues = FALSE;
  /**
   * Instruction to add a % on a stacked bar chart
   * @var boolean
   */
  protected $tagPercent = FALSE;

  function __construct($params) {
    $chart = NULL;
    if (empty($params)) {
      return $chart;
    }
    $this->values = CRM_Utils_Array::value('values', $params);
    if (! is_array($this->values) || empty($this->values)) {
      return $chart;
    }
    $this->chartTitle = CRM_Utils_Array::value('title', $params);
    $this->xlabelAngle = CRM_Utils_Array::value('xlabelAngle', $params, 0);
    $this->createChartElement();
    $this->setChartValues();
    $this->setToolTip(CRM_Utils_Array::value('tip', $params));
    $this->onClickFunName = CRM_Utils_Array::value('on_click_fun_name', $params);
  }

  /**
   *
   * Set the tool tip
   * @param string $tip
   */
  function setToolTip($tip){
    if($tip){
      $this->chartElement->set_tooltip($tip);
      return;
    }
    elseif($this->currencyValues){
      $config = CRM_Core_Config::singleton();
      $symbol = $config->defaultCurrencySymbol;
      $this->chartElement->set_tooltip("$symbol #val#");
    }
  }

  /**
     * Add main element
     */
  function addChartElement() {
  }
  /**
 * Steps to pull together chart
 */
  function buildChart() {
    $this->chart = new open_flash_chart();
    $title = new title($this->chartTitle);
    $this->chart->set_title($title);
    // add bar element to chart.
    $this->chart->add_element($this->chartElement);
  }

  /**
     *
     * @return chart object
     */
  function getChart() {
    return $this->chart;
  }
}

/**
 * Set x & y values appropriate to chart Type
 */
function setChartValues(){

}

/**
   * Base class for all bar chart actions
   *
   * @author eileen
   *
   */
class barchart extends chart {
  protected $xValues = array();
  protected $yValues = array();
  protected $xAxis = null;
  protected $yAxis = null;
  protected $yMin = 0;
  protected $yMax = 100;
  protected $ySteps = 5;
  protected $xAxisName = null;
  protected $yAxisName = null;
  protected $ylabelAngle = null;
  protected $xlabels = null;
  protected $xlabelSize = 8;
  /**
 *
 * @param array $params
 */
  function __construct($params) {
    parent::__construct($params);
    $this->setYMaxYSteps();
    $this->xAxisName = CRM_Utils_Array::value('xname', $params);
    $this->yAxisName = CRM_Utils_Array::value('yname', $params);
    $this->xlabels = CRM_Utils_Array::value('xlabels', $params);
    $this->chartTitle = CRM_Utils_Array::value('legend', $params, ts('Bar Chart'));
    // call user define function to handle on click event.
    if ($this->onClickFunName) {
      $this->chartElement->set_on_click($this->onClickFunName);
    }
  }

  /**
   * Set maximum Y value & steps based on the highest value in the array plus some rounding
   * @param array $values
   *
   * On bar this values array will be the YValues array. For stack it will be the sum of the
   * relevant values
   */
  function setYMaxYSteps($values = NULL){
    if(!$values){
      $values = $this->yValues;
    }
    if(empty($values)){
      return;
    }
    // calculate max scale for graph.
    $this->yMax = ceil(max($values));
    if ($mod = $this->yMax % (str_pad(5, strlen($this->yMax) - 1, 0))) {
      $this->yMax += str_pad(5, strlen($this->yMax) - 1, 0) - $mod;
    }
    $this->ySteps = $this->yMax / 5;
  }

  /**
     * Add main element
     */
  function createChartElement() {
    $this->chartElement = new bar_glass();
    $this->chartElement->set_values($this->yValues);
  }
  /**
 * (non-PHPdoc)
 * @see chart::buildChart()
 */
  function buildChart() {
    parent::buildChart();
    $this->buildxyAxis();
    $this->chart->set_x_axis($this->xAxis);
    $this->chart->add_y_axis($this->yAxis);
    if($this->tagPercent && !empty($this->tags)){
      $this->chart->add_element( $this->tags );
    }
  }
/**
 * Set x & y values appropriate to chart Type
 */
  function setChartValues(){
    foreach ($this->values as $xVal => $yVal) {
      $this->yValues[] = (double) $yVal[0];
      $this->xValues[] = (string) $xVal;
    }
    $this->chartElement->set_values($this->yValues);
  }
  /**
 * build x & y axis
 */
  function buildxyAxis() {
    // add x axis legend.
    if ($this->xAxisName) {
      $xLegend = new x_legend($this->xAxisName);
      $xLegend->set_style("{font-size: 13px; color:#000000; font-family: Verdana; text-align: left;}");
      $this->chart->set_x_legend($xLegend);
    }

    // add y axis legend.
    if ($this->yAxisName) {
      $yLegend = new y_legend($this->yAxisName);
      $yLegend->set_style("{font-size: 13px; color:#000000; font-family: Verdana; text-align: center;}");
      $this->chart->set_y_legend($yLegend);
    }


    // create x axis obj.
    $this->xAxis = new x_axis();
    $xLabels = $this->setXLabels();
    $this->xAxis->set_labels($xLabels);
    //create y axis and set range.
    $this->yAxis = new y_axis();
    $this->yAxis->set_range($this->yMin, $this->yMax, $this->ySteps);
  }

  /**
   *
   * Set the xLabels
   * Note we can no longer set angle for labels as openFlashPlayer
   * doesn't seem to render it anymore - presumably after being upgraded
   *  I think the fonts would need to be embedded
   * @return object x_axis_labels
   */
  function setXLabels(){
    $xLabels = new x_axis_labels();
    $xLabels->set_labels($this->xlabels);
    $xLabels->set_size($this->xlabelSize);
    if($this->xlabelAngle) {
      $xLabels->rotate($this->xlabelAngle);
    }
    return $xLabels;
  }
}

/**
   *
   * Stack bar chart class
   * @author eileen
   *
   */
class barChartStack extends barchart {
  protected $keyLabels = array();
  protected $_colours = array(
    "#C3CC38",
    "#CEA632",);
  protected $tagPercent = TRUE;
  protected $tags = array();
  function __construct($params) {
    $this->keyLabels =  $this->createKeyLabels($params['labels']);
    parent::__construct($params);
    $this->chartElement->set_colours($this->_colours);
    $x = 0;
    $this->tags = new ofc_tags();
    foreach ($this->values as $valueArray) {
      $this->chartElement->append_stack($valueArray);
      $totals[] = array_sum($valueArray);
      $tag = new ofc_tag($x, $valueArray[0]);
      if(array_sum($valueArray) == 0){
        $tag->text('N/A');
      }
      else{
        $tag->text(round($valueArray[0]/ array_sum($valueArray) * 100) . '%');
      }
      $this->tags->append_tag($tag);
      $x++;
    }
    $this->setYMaxYSteps($totals);
  }
  /**
   *
   * Set the tool tip
   * @param string $tip
   */
  function setToolTip($tip){
    if($tip){
      $this->chartElement->set_tooltip($tip);
      return;
    }
    else{
      $this->chartElement->set_tooltip(" #val# out of #total#<br>Of those who gave during <br>#x_label#");
    }
  }


/**
 *
 * @param unknown_type $labels
 * @return multitype:bar_stack_key
 */
  function createKeyLabels($labels){
    $keyLabels = array();
    foreach ($labels as $index => $label){
      $keyLabels[] = new bar_stack_key($this->_colours[$index], $label, 13);
    }
    return $keyLabels;
  }
  /**
     * Add main element
     */
  function createChartElement() {
    $this->chartElement = new bar_stack();
    $this->chartElement->set_keys($this->keyLabels);
  }

  /**
   * Set x & y values appropriate to chart Type
   */
  function setChartValues(){

  }
}


