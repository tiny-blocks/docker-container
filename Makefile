PHP_IMAGE = gustavofreze/php:8.3
DOCKER_RUN = docker run -u root --rm -it --network=tiny-blocks --name test-lib \
				-v ${PWD}:/app \
				-v ${PWD}/tests/Integration/Database/Migrations:/test-adm-migrations \
				-v /var/run/docker.sock:/var/run/docker.sock \
				-w /app ${PHP_IMAGE}

.PHONY: configure test unit-test test-no-coverage configure-test-environment review show-reports clean

configure: configure-test-environment
	@${DOCKER_RUN} composer update --optimize-autoloader

test: configure-test-environment
	@${DOCKER_RUN} composer tests

unit-test:
	@${DOCKER_RUN} composer run unit-test

test-no-coverage: configure-test-environment
	@${DOCKER_RUN} composer tests-no-coverage

configure-test-environment:
	@if ! docker network inspect tiny-blocks > /dev/null 2>&1; then \
		docker network create tiny-blocks > /dev/null 2>&1; \
	fi
	@docker volume create test-adm-migrations > /dev/null 2>&1

review:
	@${DOCKER_RUN} composer review

show-reports:
	@sensible-browser report/coverage/coverage-html/index.html

clean:
	@sudo chown -R ${USER}:${USER} ${PWD}
	@rm -rf report vendor .phpunit.cache
