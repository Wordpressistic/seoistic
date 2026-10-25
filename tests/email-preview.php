<?php
/**
 * Email template smoke-test harness: stubs the WordPress functions used by
 * EmailReportTemplate and renders three representative emails to dist/preview.
 */
error_reporting(E_ALL);

define('ABSPATH', 'C:/Users/Qbits/AppData/Local/Temp/seoistic-gh/');
define('MINUTE_IN_SECONDS', 60);

function esc_html($t){ return htmlspecialchars((string)$t, ENT_QUOTES); }
function esc_attr($t){ return htmlspecialchars((string)$t, ENT_QUOTES); }
function esc_url($u){ return htmlspecialchars((string)$u, ENT_QUOTES); }
function sanitize_text_field($t){ return trim(preg_replace('/[\r\n\t ]+/',' ',(string)$t)); }
function sanitize_hex_color($c){ return preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/',$c) ? $c : ''; }
function wp_kses_post($h){ return $h; }
function wp_date($f,$ts=null){ return date($f,$ts?:time()); }
function get_option($k,$d=false){ return $d; }
function get_bloginfo($k){ return $k==='name' ? 'WPistic Labs' : 'https://wpistic.example'; }
function home_url($p=''){ return 'https://wpistic.example'.$p; }
function admin_url($p=''){ return 'https://wpistic.example/wp-admin/'.$p; }
function __($t,$d=null){ return $t; }
function _e($t,$d=null){ echo $t; }
function esc_html__($t,$d=null){ return esc_html($t); }
function number_format_i18n($n,$d=0){ return number_format((float)$n,$d); }
function wp_parse_url($u,$c=-1){ return parse_url($u,$c); }
function sprintf_wrap(...$a){ return sprintf(...$a); }

require 'C:/Users/Qbits/AppData/Local/Temp/seoistic-gh/src/Core/EmailReportTemplate.php';

use Wpistic\Seoistic\Core\EmailReportTemplate as T;

@mkdir('C:/Users/Qbits/AppData/Local/Temp/seoistic-gh/dist/preview', 0777, true);
$out = 'C:/Users/Qbits/AppData/Local/Temp/seoistic-gh/dist/preview/';

// 1) Scheduled audit digest
$rows = [];
foreach ([['SEO Guide for Beginners',41],['Best WordPress Hosting 2026',55],['How to Speed Up WooCommerce',28]] as $r) {
    $rows[] = [T::score_chip($r[1]), esc_html($r[0]), '<a href="#" style="color:#0EA5E9;text-decoration:none;font-weight:700">Fix now</a>'];
}
$content = T::stat_cards([
    ['label'=>'Pages to review','value'=>'3','tone'=>'warn','sub'=>'threshold 70/100'],
    ['label'=>'Average score','value'=>'41/100','tone'=>'bad'],
    ['label'=>'Worst score','value'=>'28/100','tone'=>'bad'],
]) . T::section('Pages that dropped') . T::data_table(['Score','Page','Action'],$rows,15);
file_put_contents($out.'audit.html', T::render('Scheduled SEO Audit','A scheduled SEO audit found pages that need attention.','These pages scored below 70/100 in today’s scheduled SEOistic audit, after meeting or exceeding the threshold in the previous check.',$content,'Review SEO Audit','https://wpistic.example/wp-admin/admin.php?page=seoistic',['tone'=>'warn']));

// 2) CWV alert
$cards = [
    ['label'=>'LCP','value'=>'4.125 s','tone'=>'bad','sub'=>'limit 2.500 s'],
    ['label'=>'CLS','value'=>'0.310','tone'=>'bad','sub'=>'limit 0.100'],
];
file_put_contents($out.'cwv.html', T::render('Core Web Vitals Alert','One or more Core Web Vitals measurements exceeded configured limits.','The latest performance check exceeded your configured thresholds.',T::stat_cards($cards).T::section('Page checked').'<p style="margin:0"><a href="#" style="color:#0EA5E9;text-decoration:none;font-weight:700">https://wpistic.example/guide</a></p>','Open Performance Report','https://wpistic.example/wp-admin/admin.php?page=seoistic-performance',['tone'=>'bad']));

// 3) Rank report (white-label accent path)
$trows=[];
foreach([['best wordpress hosting','en-US','desktop',4.2,1.8],['wordpress seo plugin','en-US','desktop',12.0,-2.4]] as $r){
    $tone = $r[4]>0?'ok':'bad'; $txt = ($r[4]>0?'▲ ':'▼ ').number_format(abs($r[4]),1);
    $color = $r[4]>0?'#059669':'#B91C1C';
    $trows[]=[esc_html($r[0]),'en-US','desktop','<strong style="color:#0F172A">'.$r[3].'</strong>','<strong style="color:'.$color.'">'.$txt.'</strong>','Search Console data'];
}
$content3 = T::stat_cards([
    ['label'=>'Tracked','value'=>'24','tone'=>'info'],
    ['label'=>'Ranked','value'=>'19','tone'=>'neutral'],
    ['label'=>'Avg. position','value'=>'16.2','tone'=>'neutral'],
    ['label'=>'Improved','value'=>'7','tone'=>'ok'],
]) . T::data_table(['Keyword','Locale','Device','Position','Movement','Data source'],$trows,25);
file_put_contents($out.'rank.html', T::render('WPistic Labs — SEO Rank Report','Your SEO rank report for '.date('Y-m-d').'.','Generated on '.date('Y-m-d').' for https://wpistic.example',$content3,'Open Rank Tracker','https://wpistic.example/wp-admin/admin.php?page=seoistic-rank-tracker',['brand_label'=>'WPistic Labs','accent_color'=>'#9472ff']));

echo "rendered: ".strlen(file_get_contents($out.'audit.html'))." / ".strlen(file_get_contents($out.'cwv.html'))." / ".strlen(file_get_contents($out.'rank.html'))." bytes\n";
echo "OK\n";
