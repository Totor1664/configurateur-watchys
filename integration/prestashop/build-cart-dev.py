#!/usr/bin/env python3
"""Historical 0.2.2 builder intentionally disabled after native candidate changes."""
def build(output):
 raise RuntimeError('Ancien paquet 0.2.2 désactivé : utiliser dev-native-lot/build.py ; ne pas republier le code natif avec son ancien installateur.')
if __name__=='__main__':
 raise SystemExit('Ancien paquet 0.2.2 désactivé ; utiliser dev-native-lot/build.py.')
