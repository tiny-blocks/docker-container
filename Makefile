DOCKER_RUN = docker run -u root --rm -it --network=tiny-blocks --name test-lib \
				-v ${PWD}:/app \
				-v ${PWD}/tests/Integration/Database/Migrations:/test-adm-migrations \
				-v /var/run/docker.sock:/var/run/docker.sock \
				-w /app gustavofreze/php:8.3

.PHONY: configure test unit-test test-no-coverage create-volume create-network review show-reports clean

configure:
	@${DOCKER_RUN} composer update --optimize-autoloader

test: create-volume
	@${DOCKER_RUN} composer tests

unit-test:
	@${DOCKER_RUN} composer run unit-test

test-no-coverage: create-volume
	@${DOCKER_RUN} composer tests-no-coverage

create-network:
	@docker network create tiny-blocks

create-volume:
	@docker volume create test-adm-migrations

review:
	@${DOCKER_RUN} composer review

show-reports:
	@sensible-browser report/coverage/coverage-html/index.html

clean:
	@sudo chown -R ${USER}:${USER} ${PWD}
	@rm -rf report vendor .phpunit.cache
