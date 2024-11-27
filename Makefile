DOCKER_RUN = docker run -u root --rm -it --name test-lib -v ${PWD}:/app -v ${PWD}/tests/Integration/Database/Migrations:/migrations -v /var/run/docker.sock:/var/run/docker.sock -w /app gustavofreze/php:8.3

.PHONY: configure test unit-test test-no-coverage create-volume review show-reports clean

configure:
	@${DOCKER_RUN} composer update --optimize-autoloader

test: create-volume
	@${DOCKER_RUN} composer tests

unit-test:
	@${DOCKER_RUN} composer run unit-test

test-no-coverage: create-volume
	@${DOCKER_RUN} composer tests-no-coverage

create-volume:
	@docker volume create migrations

review:
	@${DOCKER_RUN} composer review

show-reports:
	@sensible-browser report/coverage/coverage-html/index.html

clean:
	@sudo chown -R ${USER}:${USER} ${PWD}
	@rm -rf report vendor .phpunit.cache
