# Makefile for docker use

APP=docker-compose exec -T app
TAPP=docker-compose exec
CAPP=docker-compose run app composer
CONSOLE=$(APP) /usr/local/bin/php bin/console

.PHONY: help install start stop destroy composer console app nginx test server

help:           ## Show this help
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'

install:        ## Setup the project using Docker and docker-compose
install: start composer-install

start:          ## Start the containers
	docker-compose up -d

stop:           ## Stop the Docker containers and remove the volumes
	docker-compose down -v

destroy:        ## Destroy all containers, volumes, networks
	docker-compose down --rmi all

composer:       ## Launch Composer
	$(CAPP) $(filter-out $@,$(MAKECMDGOALS))

composer-install:  # Install the project PHP dependencies
	$(CAPP) install -o

console:        ## Launch Console
	$(CONSOLE) $(filter-out $@,$(MAKECMDGOALS))

app:            ## Access shell of application container
	$(TAPP) app sh

nginx:          ## Access shell of nginx container
	$(TAPP) nginx sh

test:          ## Launch tests
	$(TAPP) app ./vendor/bin/simple-phpunit

server:         ## Start local PHP server (Non docker use only)
	php -S localhost:8888 -t web

%:
@: