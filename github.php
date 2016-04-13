<?php
/*
Plugin Name: GitHub Code Viewer
Version: 1.0
Plugin URI: http://www.pseudocoder.com/archives/2008/10/29/wordpress-plugin-for-showing-github-code/
Description:
Author: Matt Curry
Author URI: http://www.pseudocoder.com
*/

class github {
  var $db;
  var $table = "github";
  var $cache = array();

  function github() {
    global $wpdb;

    $this->db = $wpdb;
    $this->table = $this->db->prefix . "github";
  }

  function install() {
    $result = $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->table}` (
                               `id` int(10) unsigned NOT NULL auto_increment,
                               `url` text NOT NULL,
                               `code` text NOT NULL,
                               `updated` datetime NOT NULL default '0000-00-00 00:00:00',
                               PRIMARY KEY  (`id`)
                               )");
  }

  function uninstall() {
    $result = $this->db->query("DROP TABLE IF EXISTS `{$this->table}`");
  }

  function get_code($text='') {
    $pattern = '/(httpc:\/\/[a-z0-9\/\_\-.]{1,})/i';
    if (preg_match_all($pattern, $text, $matches)) {
      $this->__loadCache($matches[0]);

      foreach($matches[0] as $match) {
        if (isset($this->cache[$match])) {
          $code = $this->cache[$match];
        } else {
          $find = str_replace('httpc', 'https', $match);
          $code = wp_remote_fopen($find . '?raw=true');
          $code = str_replace('<', '&lt;', $code);
          $this->__setCache($match, $code);
        }

        $text = str_replace($match, $code, $text);
      }
    }

    return $text;
  }

  function __loadCache($urls) {
    $sql = sprintf('SELECT * FROM `%s`
                   WHERE url IN ("%s")',
                   $this->table,
                   implode('", "', $urls));

    $results = $this->db->get_results($sql, ARRAY_A);
    if ($results) {
      $old = array();
      foreach($results as $row) {
        if($row['updated'] < date('Y-m-d H:i:s', strtotime('-1 day'))) {
          $old[] = $row['id'];
        } else {
          $this->cache[$row['url']] = $row['code'];
        }
      }
      
      if($old) {
        $sql = sprintf('DELETE FROM `%s` WHERE id IN (%s)',
                       $this->table,
                       implode(',', $old));
        $this->db->query($sql);
      }
    }

    return true;
  }

  function __setCache($url, $code) {
    $sql = sprintf('INSERT INTO `%s` (`url`, `code`, `updated`) VALUES ("%s", "%s", "%s")',
                   $this->table,
                   $url,
                   mysql_real_escape_string($code),
                   date('Y-m-d H:i:s'));
    $result = $this->db->query($sql);
  }
}

$github =& new github();
register_activation_hook(__FILE__, array(&$github, 'install'));
register_deactivation_hook(__FILE__, array(&$github, 'uninstall'));
add_filter('the_content', array(&$github, 'get_code'), 8);
?>
