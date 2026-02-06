# Prometheus Plugin GLPI

Plugin para obter métricas do GLPI para utilização em Query's do Prometheus, esse plugin conta com métricas para tickets, usuários, notificações, entidades, documentos, categorias, plugins, cron jobs e a versão do GLPI.

## Métricas Coletadas

O plugin coleta informações de tickets, usuários, notificações, entidades, documentos, categorias, plugins e cron jobs do GLPI.

### Tickets (`glpi_total_tickets_*`)

* `glpi_total_tickets` → Número total de chamados
* `glpi_total_tickets_new` → Chamados com status Novo
* `glpi_total_tickets_atending_assigned` → Chamados Em atendimento (atribuído)
* `glpi_total_tickets_atending_planned` → Chamados Em atendimento (planejado)
* `glpi_total_tickets_pending` → Chamados Pendentes
* `glpi_total_tickets_resolved` → Chamados Solucionados
* `glpi_total_tickets_closed` → Chamados Fechados

### Usuários (`glpi_total_users_*`)

* `glpi_total_users` → Número total de usuários
* `glpi_total_users_active` → Usuários ativos
* `glpi_total_users_default_count` → Usuários com interface central (padrão)
* `glpi_total_users_not_central` → Usuários com interface simplificada

### Notificações (`glpi_total_notifications_*`)

* `glpi_total_notifications_total` → Total de notificações na fila
* `glpi_total_notifications_pending` → Notificações pendentes
* `glpi_total_notifications_high_age` → Notificações com mais de 20 minutos
* `glpi_total_notifications_high_try` → Notificações com mais de 3 tentativas

### Recursos gerais (`glpi_total_*`)

* `glpi_total_entities` → Número total de entidades
* `glpi_total_documents` → Número total de documentos
* `glpi_total_categories` → Número total de categorias ITIL
* `glpi_total_plugins` → Número total de plugins instalados

### Versão do GLPI (`glpi_version_*`)

* `glpi_version_major` → Versão major do GLPI
* `glpi_version_minor` → Versão minor do GLPI
* `glpi_version_patch` → Versão patch do GLPI

### Cron Jobs (`glpi_cronjobs_*`)

Para cada cron job configurado no GLPI são exportadas as seguintes métricas usando LABELS do prometheus com o nome do cron:

* `glpi_cronjobs_state{name=<cron_name>}` → Estado do cron (ativo=1, inativo=0)
* `glpi_cronjobs_frequency_seconds{name=<cron_name>}` → Frequência em segundos
* `glpi_cronjobs_frequency_minutes{name=<cron_name>}` → Frequência em minutos
* `glpi_cronjobs_frequency_hours{name=<cron_name>}` → Frequência em horas
* `glpi_cronjobs_last_run_seconds{name=<cron_name>}` → Última execução (em segundos)
* `glpi_cronjobs_last_run_minutes{name=<cron_name>}` → Última execução (em minutos)
* `glpi_cronjobs_last_run_hours{name=<cron_name>}` → Última execução (em horas)
* `glpi_cronjobs_last_run_days{name=<cron_name>}` → Última execução (em dias)
* `glpi_cronjobs_run_state{name=<cron_name>}` → Se o cron rodou dentro do período esperado (1 = sim, 0 = não)

## Como usar
Instale o plugin Prometheus no diretório `plugins/` ou `marketplace/` do GLPI, dependendo da forma de instalação desejada.

Ative o plugin no painel do GLPI.

Acesse a rota:
```
http://<glpi_url>/plugins/prometheus/metrics.php
```
ou
```
http://<glpi_url>/marketplace/prometheus/metrics.php
```

para visualizar as métricas. Configure o Prometheus adicionando um novo scrape_config no `prometheus.yml`:
```yml
scrape_configs:
  - job_name: '<job_name>'
    metrics_path: '/<plugins|marketplace>/prometheus/metrics.php'
    static_configs:
      - targets: ['<glpi_dns>:<glpi_porta>']
```
