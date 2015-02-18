{*
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
*}

{literal}
  <script type="text/javascript" src="https://www.google.com/jsapi"></script>
  <script type="text/javascript">
    google.load("visualization", "1", {packages:["corechart"]});
    google.setOnLoadCallback(function(){
      var graphData = [
          [{/literal}'{$chartsLabels.0}' , '{$chartsLabels.1}'],
          {foreach from=$data item='value' key='key'}
            ['{$key}' , {$value}],
          {/foreach}
        ];
      {literal}
      drawChart(graphData);
   });

    function drawChart(graphData ) {
      var data = google.visualization.arrayToDataTable(graphData);
      var options = {
         title: {/literal}'{$chartInfo.legend}'{literal},
         backgroundColor: '#F8F8D8',
         colors: ['#C3CC38','#C8B935'],
         chartArea: {height: '70%'},
         legend: {position: 'bottom'}
       };
       var chart = new google.visualization.PieChart(document.getElementById('chart_div'));
       chart.draw(data, options);
     }
 </script>
{/literal}

