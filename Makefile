.PHONY: build buildWithDev server deploy start-datastore-emulator

OPTIONS:=
DATASTORE_EMULATOR_HOST:=localhost:8081
DATASTORE_EMULATOR_OPTIONS:=--store-on-disk --data-dir=tmp/datastore

build:
	composer install -o --no-dev

buildWithDev:
	composer install -o

server: build start-datastore-emulator
	php -S localhost:8080 -t public/

deploy: credential/bh-app.json
	gcloud app deploy --project=bh-ani-watch --promote --stop-previous-version $(OPTIONS)
	@echo "\033[1;33mDeploy done.\033[m"

credential/bh-app.json:
	@echo "credential/bh-app.json 不存在，無法 deploy，請至 google console 重新建立" && false

start-datastore-emulator:
	$(eval export DATASTORE_EMULATOR_HOST=$(DATASTORE_EMULATOR_HOST))
	@curl -s -X POST $(DATASTORE_EMULATOR_HOST)/shutdown && echo "已關閉原有的 datastore 模擬器" || echo "沒有啟用中的 datastore 模擬器"
	@echo "\033[1;33m來把 datastore 模擬器開在 $(DATASTORE_EMULATOR_HOST)\033[m"
	gcloud beta emulators datastore start $(DATASTORE_EMULATOR_OPTIONS)  --host-port='$(DATASTORE_EMULATOR_HOST)' 2> tmp/datastore_emulator.log &
	@bin/check-datastore-emulator $(DATASTORE_EMULATOR_HOST)

stop-datastore-emulator:
	@echo "\033[1;33mShutting down datastore emulator at $(DATASTORE_EMULATOR_HOST)\033[m"
	curl -X POST '$(DATASTORE_EMULATOR_HOST)'/shutdown
