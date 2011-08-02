all: tests

tests:
	cd t && $(MAKE)

clean:

.PHONY: all tests clean
