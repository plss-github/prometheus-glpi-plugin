<?php

include "../../inc/includes.php";

$pluginName = "prometheus";
$pluginHandler = new Plugin();

if (!$pluginHandler->isInstalled($pluginName)) {
  http_response_code(404);
  exit();
}

header(
  "Content-Type: text/plain; version=0.0.4; charset=utf-8; escaping=underscores",
);

if (!Plugin::isPluginActive($pluginName)) {
  echo "Plugin '{$pluginName}' is inactive!";
  exit();
}

function get_data()
{
  global $DB;

  $sql = "
    SELECT
      COUNT(*) AS total,
      COALESCE(SUM(CASE WHEN sent_time IS NULL AND is_deleted = 0 THEN 1 ELSE 0 END), 0) AS pending,
      COALESCE(SUM(
        CASE
          WHEN sent_time IS NULL
            AND is_deleted = 0
            AND TIMESTAMPDIFF(SECOND, create_time, NOW()) > 1200
          THEN 1
          ELSE 0
        END
      ), 0) AS high_age,
      COALESCE(SUM(
        CASE
          WHEN sent_time IS NULL
            AND is_deleted = 0
            AND sent_try > 3
          THEN 1
          ELSE 0
        END
      ), 0) AS high_try
    FROM glpi_queuednotifications
    WHERE recipient LIKE '%@%';
  ";

  $result = $DB->doQuery($sql);
  $notifications = $result->fetch_assoc();

  $sql = "
    SELECT
      COUNT(*) AS total,
      COALESCE(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END), 0) AS new,
      COALESCE(SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END), 0) AS atending_assigned,
      COALESCE(SUM(CASE WHEN status = 3 THEN 1 ELSE 0 END), 0) AS atending_planned,
      COALESCE(SUM(CASE WHEN status = 4 THEN 1 ELSE 0 END), 0) AS pending,
      COALESCE(SUM(CASE WHEN status = 5 THEN 1 ELSE 0 END), 0) AS resolved,
      COALESCE(SUM(CASE WHEN status = 6 THEN 1 ELSE 0 END), 0) AS closed
    FROM glpi_tickets;
  ";

  $result = $DB->doQuery($sql);
  $tickets = $result->fetch_assoc();

  $sql = "
    SELECT
      COUNT(*) AS total,
      COALESCE(SUM(is_active), 0) AS active
    FROM glpi_users;
  ";

  $result = $DB->doQuery($sql);
  $users_data = $result->fetch_assoc();

  $sql = "
    SELECT
      u.id AS user_id,
      GROUP_CONCAT(p.interface SEPARATOR ', ') AS profiles_interfaces
    FROM
      glpi_users u
    JOIN
      glpi_profiles_users pu ON u.id = pu.users_id
    JOIN
      glpi_profiles p ON pu.profiles_id = p.id
    WHERE
      u.is_active = 1
    GROUP BY
      u.id;
  ";

  $result = $DB->doQuery($sql);
  $users = $result->fetch_all(MYSQLI_ASSOC);

  $sql = "
    SELECT id, name, frequency, lastrun, state FROM glpi_crontasks;
  ";

  $result = $DB->doQuery($sql);
  $crons = $result->fetch_all(MYSQLI_ASSOC);

  $default_view_user_count = 0;
  $basic_view_user_count = 0;
  foreach ($users as $user) {
    if (strpos($user["profiles_interfaces"], "central") !== false) {
      $default_view_user_count++;
    } else {
      $basic_view_user_count++;
    }
  }

  $total_plugins = countElementsInTable("glpi_plugins");
  $total_entities = countElementsInTable("glpi_entities");
  $total_docs = countElementsInTable("glpi_documents");
  $total_categories = countElementsInTable("glpi_itilcategories");

  return [
    "tickets" => $tickets,
    "notifications" => $notifications,
    "users" => [
      "total" => $users_data["total"],
      "active" => $users_data["active"],
      "default_view_count" => $default_view_user_count,
      "basic_view_count" => $basic_view_user_count,
    ],
    "total_plugins" => $total_plugins,
    "total_entities" => $total_entities,
    "total_docs" => $total_docs,
    "total_categories" => $total_categories,
    "cron_jobs" => $crons,
  ];
}

$data = get_data();

$metrics = [
  "glpi_cronjobs_state" => [
    "help" => "Estado do cronjob (ativo = 1, inativo = 0)",
    "type" => "gauge",
  ],
  "glpi_cronjobs_frequency_seconds" => [
    "help" => "Frequência do cronjob em segundos",
    "type" => "gauge",
  ],
  "glpi_cronjobs_frequency_minutes" => [
    "help" => "Frequência do cronjob em minutos",
    "type" => "gauge",
  ],
  "glpi_cronjobs_frequency_hours" => [
    "help" => "Frequência do cronjob em horas",
    "type" => "gauge",
  ],
  "glpi_cronjobs_last_run_seconds" => [
    "help" => "Última execução do cronjob (timestamp Unix em segundos)",
    "type" => "gauge",
  ],
  "glpi_cronjobs_last_run_minutes" => [
    "help" => "Última execução do cronjob (em minutos)",
    "type" => "gauge",
  ],
  "glpi_cronjobs_last_run_hours" => [
    "help" => "Última execução do cronjob (em horas)",
    "type" => "gauge",
  ],
  "glpi_cronjobs_last_run_days" => [
    "help" => "Última execução do cronjob (em dias)",
    "type" => "gauge",
  ],
  "glpi_cronjobs_run_state" => [
    "help" =>
      "Indica se o cronjob rodou no período esperado (1 = sim, 0 = não)",
    "type" => "gauge",
  ],
];

$version = "0.0.0";
if (defined("GLPI_VERSION")) {
  $version = GLPI_VERSION;
}

$version = explode(".", $version);

echo "# HELP glpi_total_entities Número total de entidades\n";
echo "# TYPE glpi_total_entities counter\n";
echo "glpi_total_entities {$data["total_entities"]}\n\n";

echo "# HELP glpi_total_documents Número total de documentos\n";
echo "# TYPE glpi_total_documents counter\n";
echo "glpi_total_documents {$data["total_docs"]}\n\n";

echo "# HELP glpi_total_categories Número total de categorias\n";
echo "# TYPE glpi_total_categories counter\n";
echo "glpi_total_categories {$data["total_categories"]}\n\n";

echo "# HELP glpi_total_users Número total de usuários\n";
echo "# TYPE glpi_total_users counter\n";
echo "glpi_total_users {$data["users"]["total"]}\n\n";

echo "# HELP glpi_total_users_active Número total de usuários ativos\n";
echo "# TYPE glpi_total_users_active counter\n";
echo "glpi_total_users_active {$data["users"]["active"]}\n\n";

echo "# HELP glpi_total_users_default_count Número total de usuários com interface padrão\n";
echo "# TYPE glpi_total_users_default_count counter\n";
echo "glpi_total_users_default_count {$data["users"]["default_view_count"]}\n\n";

echo "# HELP glpi_total_users_not_central Número total de usuários com interface simplificada\n";
echo "# TYPE glpi_total_users_not_central counter\n";
echo "glpi_total_users_not_central {$data["users"]["basic_view_count"]}\n\n";

echo "# HELP glpi_total_tickets Número total de chamados\n";
echo "# TYPE glpi_total_tickets counter\n";
echo "glpi_total_tickets {$data["tickets"]["total"]}\n\n";

echo "# HELP glpi_total_tickets_new Número total de chamados com status: `Novo`\n";
echo "# TYPE glpi_total_tickets_new counter\n";
echo "glpi_total_tickets_new {$data["tickets"]["new"]}\n\n";

echo "# HELP glpi_total_tickets_atending_assigned Número total de chamados com status: `Em atendimento (atribuído)`\n";
echo "# TYPE glpi_total_tickets_atending_assigned counter\n";
echo "glpi_total_tickets_atending_assigned {$data["tickets"]["atending_assigned"]}\n\n";

echo "# HELP glpi_total_tickets_atending_planned Número total de chamados com status: `Em atendimento (planejado)`\n";
echo "# TYPE glpi_total_tickets_atending_planned counter\n";
echo "glpi_total_tickets_atending_planned {$data["tickets"]["atending_planned"]}\n\n";

echo "# HELP glpi_total_tickets_pending Número total de chamados com status: `Pendente`\n";
echo "# TYPE glpi_total_tickets_pending counter\n";
echo "glpi_total_tickets_pending {$data["tickets"]["pending"]}\n\n";

echo "# HELP glpi_total_tickets_resolved Número total de chamados com status: `Solucionado`\n";
echo "# TYPE glpi_total_tickets_resolved counter\n";
echo "glpi_total_tickets_resolved {$data["tickets"]["resolved"]}\n\n";

echo "# HELP glpi_total_tickets_closed Número total de chamados com status: `Fechado`\n";
echo "# TYPE glpi_total_tickets_closed counter\n";
echo "glpi_total_tickets_closed {$data["tickets"]["closed"]}\n\n";

echo "# HELP glpi_total_plugins Número total de plugins\n";
echo "# TYPE glpi_total_plugins counter\n";
echo "glpi_total_plugins {$data["total_plugins"]}\n\n";

echo "# HELP glpi_total_notifications Total de notificações na fila\n";
echo "# TYPE glpi_total_notifications counter\n";
echo "glpi_total_notifications {$data["notifications"]["total"]}\n\n";

echo "# HELP glpi_total_notifications_pending Notificações pendentes\n";
echo "# TYPE glpi_total_notifications_pending gauge\n";
echo "glpi_total_notifications_pending {$data["notifications"]["pending"]}\n\n";

echo "# HELP glpi_total_notifications_high_age Notificações com mais de 20 minutos\n";
echo "# TYPE glpi_total_notifications_high_age gauge\n";
echo "glpi_total_notifications_high_age {$data["notifications"]["high_age"]}\n\n";

echo "# HELP glpi_total_notifications_high_try Notificações com mais de 3 tentativas\n";
echo "# TYPE glpi_total_notifications_high_try gauge\n";
echo "glpi_total_notifications_high_try {$data["notifications"]["high_try"]}\n\n";

foreach ($metrics as $metric => $meta) {
  echo "# HELP {$metric} {$meta["help"]}\n";
  echo "# TYPE {$metric} {$meta["type"]}\n\n";
}

foreach ($data["cron_jobs"] as $cron) {
  $name = $cron["name"];
  $state = (int) $cron["state"];
  $frequency = (int) $cron["frequency"];
  $lastRun = strtotime($cron["lastrun"]);

  $values = [
    "glpi_cronjobs_state" => $state,
    "glpi_cronjobs_frequency_seconds" => $frequency,
    "glpi_cronjobs_frequency_minutes" => $frequency / 60,
    "glpi_cronjobs_frequency_hours" => $frequency / 60 / 60,
    "glpi_cronjobs_last_run_seconds" => $lastRun,
    "glpi_cronjobs_last_run_minutes" => $lastRun / 60,
    "glpi_cronjobs_last_run_hours" => $lastRun / 60 / 60,
    "glpi_cronjobs_last_run_days" => $lastRun / 60 / 60 / 24,
    "glpi_cronjobs_run_state" =>
      $state === 1 ? (int) ($lastRun + $frequency >= time()) : 1,
  ];

  foreach ($values as $metric => $value) {
    echo "{$metric}{name=\"{$name}\"} {$value}\n";
  }
  echo "\n";
}

echo "# HELP glpi_version_major Versão major atual do GLPI do rodando\n";
echo "# TYPE glpi_version_major counter\n";
echo "glpi_version_major {$version[0]}\n\n";

echo "# HELP glpi_version_minor Versão minor atual do GLPI do rodando\n";
echo "# TYPE glpi_version_minor counter\n";
echo "glpi_version_minor {$version[1]}\n\n";

echo "# HELP glpi_version_patch Versão patch atual do GLPI do rodando\n";
echo "# TYPE glpi_version_patch counter\n";
echo "glpi_version_patch {$version[2]}\n\n";
