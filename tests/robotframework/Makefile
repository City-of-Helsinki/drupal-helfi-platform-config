STONEHENGE_PATH ?= ${HOME}/stonehenge
PROJECT_DIR ?= ${GITHUB_WORKSPACE}
SITE_PREFIX ?= /

SETUP_ROBO_TARGETS :=
CI_POST_INSTALL_TARGETS :=

SETUP_ROBO_TARGETS += install-stonehenge start-stonehenge set-permissions up composer-install fix-files-permissions install-drupal post-install-tasks

install-stonehenge: $(STONEHENGE_PATH)/.git

$(STONEHENGE_PATH)/.git:
	git clone -b 4.x https://github.com/druidfi/stonehenge.git $(STONEHENGE_PATH)

PHONY += start-stonehenge
start-stonehenge:
	cd $(STONEHENGE_PATH) && COMPOSE_FILE=docker-compose.yml make up

PHONY += install-drupal
install-drupal:
	$(call docker_run_ci,app,drush si minimal -y)
	$(call docker_run_ci,app,drush cr)
	$(call docker_run_ci,app,drush si minimal --existing-config -y)
	$(call docker_run_ci,app,drush deploy)

PHONY += post-install-tasks
post-install-tasks:
	$(call docker_run_ci,app,drush upwd helfi-admin Test_Automation)
	$(call docker_run_ci,app,drush en helfi_example_content syslog -y)
	$(call docker_run_ci,app,drush helfi:migrate-fixture tpr_unit --publish)
	$(call docker_run_ci,app,drush helfi:migrate-fixture tpr_service --publish)
	$(call docker_run_ci,app,drush helfi:migrate-fixture tpr_errand_service --publish)
	$(call docker_run_ci,app,drush helfi:migrate-fixture tpr_service_channel --publish)
	$(call docker_run_ci,app,drush pmu editoria11y -y)

PHONY += set-permissions
set-permissions:
	chmod 777 /home/runner/.cache/composer -R
	chmod 777 -R $(PROJECT_DIR)

PHONY += fix-files-permission
fix-files-permission:
	mkdir $(PROJECT_DIR)public/sites/default/files -p && chmod 777 -R $(PROJECT_DIR)public/sites/default/files

define docker_run_ci
	docker compose exec -T $(1) sh -c "$(2)"
endef

PHONY += setup-robo
setup-robo: $(SETUP_ROBO_TARGETS)

PHONY += run-robo-tests
run-robo-tests:
	$(call docker_run_ci,robo,cd /app/public/modules/contrib/helfi_platform_config/tests/robotframework && chmod +x run_all_tests.sh && PREFIX=$(SITE_PREFIX) BASE_URL=$(DRUPAL_HOSTNAME) ./run_all_tests.sh)
