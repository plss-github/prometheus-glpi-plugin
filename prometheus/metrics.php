<?php

include('../../inc/includes.php');

$pluginName = "prometheus";
$pluginHandler = new Plugin();

if (!$pluginHandler->isInstalled($pluginName)) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; version=0.0.4; charset=utf-8; escaping=underscores');

if (!Plugin::isPluginActive($pluginName)) {
    echo "Plugin '{$pluginName}' is inactive!";
    exit;
}

function get_data() {
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
    
    $result = $DB->query($sql);
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
    
    $result = $DB->query($sql);
    $tickets = $result->fetch_assoc();

    $sql = "
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(is_active), 0) AS active
        FROM glpi_users;
    ";

    $result = $DB->query($sql);
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

    $result = $DB->query($sql);
    $users = $result->fetch_all(MYSQLI_ASSOC);

    $sql = "
        SELECT id, name, frequency, lastrun, state FROM glpi_crontasks;
    ";

    $result = $DB->query($sql);
    $crons = $result->fetch_all(MYSQLI_ASSOC);

    $default_view_user_count = 0;
    $basic_view_user_count = 0;
    foreach ($users as $user) {
        if (strpos($user['profiles_interfaces'], 'central') !== false) {
            $default_view_user_count++;
        } else {
            $basic_view_user_count++;
        }
    }
    
    $total_plugins = countElementsInTable('glpi_plugins');
    $total_entities = countElementsInTable('glpi_entities');
    $total_docs = countElementsInTable('glpi_documents');
    $total_categories = countElementsInTable('glpi_itilcategories');

    return [
        "tickets" => $tickets,
        "notifications" => $notifications,
        "users" => [
            "total" => $users_data['total'],
            "active" => $users_data['active'],
            "default_view_count" => $default_view_user_count,
            "basic_view_count" => $basic_view_user_count
        ],
        "total_plugins" => $total_plugins,
        "total_entities" => $total_entities,
        "total_docs" => $total_docs,
        "total_categories" => $total_categories,
        "cron_jobs" => $crons
    ];
}

$data = get_data();

echo "# HELP glpi_total_entities Número total de entidades\n";
echo "# TYPE glpi_total_entities counter\n";
echo "glpi_total_entities {$data['total_entities']}\n\n";

echo "# HELP glpi_total_documents Número total de documentos\n";
echo "# TYPE glpi_total_documents counter\n";
echo "glpi_total_documents {$data['total_docs']}\n\n";

echo "# HELP glpi_total_categories Número total de categorias\n";
echo "# TYPE glpi_total_categories counter\n";
echo "glpi_total_categories {$data['total_categories']}\n\n";

echo "# HELP glpi_total_users Número total de usuários\n";
echo "# TYPE glpi_total_users counter\n";
echo "glpi_total_users {$data['users']['total']}\n\n";

echo "# HELP glpi_total_users_active Número total de usuários ativos\n";
echo "# TYPE glpi_total_users_active counter\n";
echo "glpi_total_users_active {$data['users']['active']}\n\n";

echo "# HELP glpi_total_users_default_count Número total de usuários com interface padrão\n";
echo "# TYPE glpi_total_users_default_count counter\n";
echo "glpi_total_users_default_count {$data['users']['default_view_count']}\n\n";

echo "# HELP glpi_total_users_not_central Número total de usuários com interface simplificada\n";
echo "# TYPE glpi_total_users_not_central counter\n";
echo "glpi_total_users_not_central {$data['users']['basic_view_count']}\n\n";

echo "# HELP glpi_total_tickets Número total de chamados\n";
echo "# TYPE glpi_total_tickets counter\n";
echo "glpi_total_tickets {$data['tickets']['total']}\n\n";

echo "# HELP glpi_total_new_tickets Número total de chamados com status: `Novo`\n";
echo "# TYPE glpi_total_new_tickets counter\n";
echo "glpi_total_new_tickets {$data['tickets']['new']}\n\n";

echo "# HELP glpi_total_atending_assigned_tickets Número total de chamados com status: `Em atendimento (atribuído)`\n";
echo "# TYPE glpi_total_atending_assigned_tickets counter\n";
echo "glpi_total_atending_assigned_tickets {$data['tickets']['atending_assigned']}\n\n";

echo "# HELP glpi_total_atending_planned_tickets Número total de chamados com status: `Em atendimento (planejado)`\n";
echo "# TYPE glpi_total_atending_planned_tickets counter\n";
echo "glpi_total_atending_planned_tickets {$data['tickets']['atending_planned']}\n\n";

echo "# HELP glpi_total_pending_tickets Número total de chamados com status: `Pendente`\n";
echo "# TYPE glpi_total_pending_tickets counter\n";
echo "glpi_total_pending_tickets {$data['tickets']['pending']}\n\n";

echo "# HELP glpi_total_resolved_tickets Número total de chamados com status: `Solucionado`\n";
echo "# TYPE glpi_total_resolved_tickets counter\n";
echo "glpi_total_resolved_tickets {$data['tickets']['resolved']}\n\n";

echo "# HELP glpi_total_closed_tickets Número total de chamados com status: `Fechado`\n";
echo "# TYPE glpi_total_closed_tickets counter\n";
echo "glpi_total_closed_tickets {$data['tickets']['closed']}\n\n";

echo "# HELP glpi_total_plugins Número total de plugins\n";
echo "# TYPE glpi_total_plugins counter\n";
echo "glpi_total_plugins {$data['total_plugins']}\n\n";

echo "# HELP glpi_total_notifications Total de notificações na fila\n";
echo "# TYPE glpi_total_notifications counter\n";
echo "glpi_total_notifications {$data['notifications']['total']}\n\n";

echo "# HELP glpi_total_pending_notifications Notificações pendentes\n";
echo "# TYPE glpi_total_pending_notifications gauge\n";
echo "glpi_total_pending_notifications {$data['notifications']['pending']}\n\n";

echo "# HELP glpi_total_high_age_notifications Notificações com mais de 20 minutos\n";
echo "# TYPE glpi_total_high_age_notifications gauge\n";
echo "glpi_total_high_age_notifications {$data['notifications']['high_age']}\n\n";

echo "# HELP glpi_total_high_try_notifications Notificações com mais de 3 tentativas\n";
echo "# TYPE glpi_total_high_try_notifications gauge\n";
echo "glpi_total_high_try_notifications {$data['notifications']['high_try']}\n\n";

echo "# HELP glpi_crontasks_state Estado do cronjob (ativo = 1, inativo = 0)\n";
echo "# TYPE glpi_crontasks_state gauge\n";

echo "# HELP glpi_crontasks_frequency_seconds Frequência do cronjob em segundos\n";
echo "# TYPE glpi_crontasks_frequency_seconds gauge\n";

echo "# HELP glpi_crontasks_frequency_minutes Frequência do cronjob em minutos\n";
echo "# TYPE glpi_crontasks_frequency_minutes gauge\n";

echo "# HELP glpi_crontasks_frequency_hours Frequência do cronjob em horas\n";
echo "# TYPE glpi_crontasks_frequency_hours gauge\n";

echo "# HELP glpi_crontasks_last_run_seconds Última execução do cronjob (timestamp Unix em segundos)\n";
echo "# TYPE glpi_crontasks_last_run_seconds gauge\n";

echo "# HELP glpi_crontasks_last_run_minutes Última execução do cronjob (em minutos)\n";
echo "# TYPE glpi_crontasks_last_run_minutes gauge\n";

echo "# HELP glpi_crontasks_last_run_hours Última execução do cronjob (em horas)\n";
echo "# TYPE glpi_crontasks_last_run_hours gauge\n";

echo "# HELP glpi_crontasks_last_run_days Última execução do cronjob (em dias)\n";
echo "# TYPE glpi_crontasks_last_run_days gauge\n";

echo "# HELP glpi_crontasks_run_state Indica se o cronjob rodou no período esperado (1 = sim, 0 = não)\n";
echo "# TYPE glpi_crontasks_run_state gauge\n";

foreach ($data['cron_jobs'] as $cron) {
    $name = $cron['name'];
    $state = $cron['state'];
    $frequency = $cron['frequency'];
    $lastrun = strtotime($cron['lastrun']) * 1;


    echo "glpi_crontasks_state{name=\"{$name}\"} {$state}\n\n";

    echo "glpi_crontasks_frequency_seconds{name=\"{$name}\"} {$frequency}\n\n";


    echo "glpi_crontasks_frequency_minutes{name=\"{$name}\"} " . ($frequency / 60) . "\n\n";


    echo "glpi_crontasks_frequency_hours{name=\"{$name}\"} " . ($frequency / 60 / 60) . "\n\n";


    echo "glpi_crontasks_last_run_seconds{name=\"{$name}\"} {$lastrun}\n\n";

    echo "glpi_crontasks_last_run_minutes{name=\"{$name}\"} " . ($lastrun / 60) . "\n\n";

    echo "glpi_crontasks_last_run_hours{name=\"{$name}\"} " . ($lastrun / 60 / 60) . "\n\n";

    echo "glpi_crontasks_last_run_days{name=\"{$name}\"} " . ($lastrun / 60 / 60 / 24) . "\n\n";

    $run_state = 1;
    if ($state == 1) {
        $run_state = intval(($lastrun + $frequency) >= time());
    }
    echo "glpi_crontasks_run_state{name=\"{$name}\"} {$run_state}\n\n";
}
