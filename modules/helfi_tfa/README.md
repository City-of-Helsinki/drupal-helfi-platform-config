# Hel.fi: TFA

## Installation

1. Generate a random 256-bit key: `dd if=/dev/urandom bs=32 count=1 | base64 -i -`
2. Add a new secret called `TFA-ENCRYPTION-KEY` to Azure KeyVault and copy the key as value.
