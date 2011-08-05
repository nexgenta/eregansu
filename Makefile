all: tests docs

tests:
	cd t && $(MAKE)

docs:
	rm -rf docs
	php -f gendoc/gendoc.php -- -o docs .

clean:

.PHONY: all tests docs clean
