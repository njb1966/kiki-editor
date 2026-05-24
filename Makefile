.PHONY: run build smoke install clean

run:
	python3 editor.py

build:
	bash packaging/build-debian.sh

smoke:
	bash packaging/smoke-bundle.sh dist/kiki-editor

install:
	bash packaging/install-debian.sh dist/kiki-editor/kiki-editor

clean:
	rm -rf build dist __pycache__
