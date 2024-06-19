<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */
namespace CalendarPluginRRuleExt;
/**
 * Description of RsetRule
 *
 * @author g.ermolaev
 */
class RSetExt extends \RRule\RSet {
    //put your code here
    public function rfcString() {
        $t_rfc_string = '';
        foreach( $this->getRRules() as $t_rrule ) {
            $t_rfc_string .= $t_rrule->rfcString();
        }
        foreach( $this->getExDates() as $t_date ) {
            $t_rfc_string .= sprintf( "\nEXDATE:%s", $t_date->format( 'Ymd\THis\Z' ) );
        }
        return $t_rfc_string;
    }
}
