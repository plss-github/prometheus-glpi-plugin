<?php

define('PLUGIN_PROMETHEUS_VERSION', '1.0.0');

function plugin_init_prometheus() {
  global $PLUGIN_HOOKS;

  $PLUGIN_HOOKS['csrf_compliant']['prometheus'] = true;

}

function plugin_version_prometheus() {
  return [
    'name' => 'Prometheus GLPI',
    'version' => PLUGIN_PROMETHEUS_VERSION,
    'author' => '<a href="https://github.com/O-Ampris">Ampris</a>',
    'license' => 'GPLv2+',
    'homepage' => 'https://github.com/plss-github/prometheus-glpi-plugin',
  ];
}

function plugin_prometheus_check_prerequisites() {
  return true;
}

function plugin_prometheus_check_config($verbose = false) {
  if (true) {
    return true;
  }

  if ($verbose) {
    echo __('Installed / not configured', 'prometheus');
  }
  return false;
}