prefix ?= /usr/local
phpincdir ?= /usr/share/php

all: tests docs

tests:
	cd t && $(MAKE)

docs:
	rm -rf docs
	php -f gendoc/gendoc.php -- -o docs .

install: all
	mkdir -p $(DESTDIR)$(phpincdir)/eregansu
	cp -Rf . $(DESTDIR)$(phpincdir)/eregansu

clean:

.PHONY: all tests docs clean install
