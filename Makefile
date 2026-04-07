IMAGE_NAME = stream-hub-symfony-bundle
WORKSPACE_DIR = /var/www/stream-hub
HOST_WORKSPACE_DIR = $(abspath ..)
PACKAGE_DIR = $(WORKSPACE_DIR)/stream-hub-symfony-bundle

build:
	docker build -t $(IMAGE_NAME) .

install: build
	docker run --rm -v $(HOST_WORKSPACE_DIR):$(WORKSPACE_DIR) -w $(PACKAGE_DIR) $(IMAGE_NAME) composer install --no-interaction --prefer-dist

test: build
	docker run --rm -v $(HOST_WORKSPACE_DIR):$(WORKSPACE_DIR) -w $(PACKAGE_DIR) $(IMAGE_NAME) vendor/bin/phpunit

deptrac: build
	docker run --rm -v $(HOST_WORKSPACE_DIR):$(WORKSPACE_DIR) -w $(PACKAGE_DIR) $(IMAGE_NAME) vendor/bin/deptrac analyse

ash: build
	docker run -it --rm -v $(HOST_WORKSPACE_DIR):$(WORKSPACE_DIR) -w $(PACKAGE_DIR) $(IMAGE_NAME) ash

clean:
	docker rmi $(IMAGE_NAME) || true
