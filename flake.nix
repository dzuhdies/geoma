{
  description = "Geoma - Laravel 13 + Vite dev environment";

  inputs.nixpkgs.url = "github:NixOS/nixpkgs/nixos-unstable";

  outputs = { self, nixpkgs }:
    let
      system = "x86_64-linux";
      pkgs = nixpkgs.legacyPackages.${system};
    in {
      devShells.${system}.default = pkgs.mkShell {
        buildInputs = [
          pkgs.php83                    # composer.json requires ^8.3
          pkgs.php83Packages.composer
          pkgs.nodejs_22                # Vite 8 needs a modern Node; npm is included
          pkgs.sqlite
        ];

        shellHook = ''
          echo "✅ Geoma dev shell loaded"
          echo "PHP:      $(php -v | head -n1)"
          echo "Composer: $(composer --version)"
          echo "Node:     $(node -v)"
        '';
      };
    };
}
