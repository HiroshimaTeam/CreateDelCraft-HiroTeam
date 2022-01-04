<?php
/**
 * ██╗░░██╗██╗██████╗░░█████╗░████████╗███████╗░█████╗░███╗░░░███╗
 * ██║░░██║██║██╔══██╗██╔══██╗╚══██╔══╝██╔════╝██╔══██╗████╗░████║
 * ███████║██║██████╔╝██║░░██║░░░██║░░░█████╗░░███████║██╔████╔██║
 * ██╔══██║██║██╔══██╗██║░░██║░░░██║░░░██╔══╝░░██╔══██║██║╚██╔╝██║
 * ██║░░██║██║██║░░██║╚█████╔╝░░░██║░░░███████╗██║░░██║██║░╚═╝░██║
 * ╚═╝░░╚═╝╚═╝╚═╝░░╚═╝░╚════╝░░░░╚═╝░░░╚══════╝╚═╝░░╚═╝╚═╝░░░░░╚═╝
 * CreateDelCraft-HiroTeam By WillyDuGang
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/
 *
 *
 * GitHub: https://github.com/HiroshimaTeam/CreateDelCraft-HiroTeam
 */

namespace HiroTeam\CreateDelCraft;

use pocketmine\crafting\CraftingManager;
use pocketmine\crafting\CraftingManagerFromDataHelper;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\network\mcpe\cache\CraftingDataCache;
use pocketmine\plugin\PluginBase;
use Webmozart\PathUtil\Path;

class CreateDelCraftMain extends PluginBase implements Listener
{

    private CraftingManager $newCratingManger;
    private array $deletedCraft = [];

    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        $playerNetwork = $player->getNetworkSession();
        $playerNetwork->sendDataPacket(CraftingDataCache::getInstance()->getCache($this->newCratingManger));
    }

    public function playerCraftEvent(CraftItemEvent $event)
    {
        $recipe = $event->getRecipe();
        $shape = $recipe->getShape();
        foreach ($this->deletedCraft as $deletedCraft) {
            if ($shape !== $deletedCraft['shape']) continue;
            foreach ($recipe->getResults() as $result) {
                if ($deletedCraft['output'] !== $result->getId()) continue;
                $sameIngredientQuantity = 0;
                for ($y = 0; $y < $recipe->getHeight(); ++$y) {
                    for ($x = 0; $x < $recipe->getWidth(); ++$x) {
                        $ingredient = $recipe->getIngredient($x, $y);
                        if ($ingredient->isNull()) continue;
                        $targetItem = $deletedCraft['input'][$shape[$y][$x]];
                        if ($targetItem['id'] === $ingredient->getId()) {
                            $sameIngredientQuantity++;
                        }
                    }
                }
                if ($sameIngredientQuantity === count($recipe->getIngredientList())) {
                    $event->cancel();
                    break 2;
                }
            }
        }
    }

    protected function onEnable(): void
    {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->initCustomCraft();
    }

    private function initCustomCraft()
    {
        $originalRecipePath = Path::join(\pocketmine\BEDROCK_DATA_PATH, "recipes.json");
        $recipes = json_decode(file_get_contents($originalRecipePath), true);
        $config = $this->getConfig();
        $deleteCraft = $config->get('deleteCraft');
        foreach ($recipes['shaped'] as $index => $recipe) {
            $id = $recipe['output'][0]['id'];
            if (!in_array($id, $deleteCraft)) continue;
            unset($recipes['shaped'][$index]);
            $this->deletedCraft[] = [
                'output' => $id,
                'input' => $recipe['input'],
                'shape' => $recipe['shape']
            ];
        }
        foreach ($config->get('addCraft') as $recipe) {
            $outputIdMetaAmount = explode(':', $recipe['output']);
            $input = [];
            foreach ($recipe['input'] as $index => $idMeta) {
                $idMeta = explode(':', $idMeta);
                $input[$index] = [
                    'id' => (int)$idMeta[0],
                    'damage' => (int)$idMeta[1]
                ];
            }
            $recipes['shaped'][] = [
                'block' => 'crafting_table',
                'input' => $input,
                'shape' => $recipe['shape'],
                'priority' => 0,
                'output' => [[
                    'id' => (int)$outputIdMetaAmount[0],
                    'damage' => (int)$outputIdMetaAmount[1],
                    'count' => (int)$outputIdMetaAmount[2]
                ]]
            ];
        }
        file_put_contents($this->getDataFolder() . 'recipes_cache.json', json_encode($recipes));
        $this->newCratingManger = CraftingManagerFromDataHelper::make(Path::join($this->getDataFolder() . 'recipes_cache.json'));
        $craftingManager = $this->getServer()->getCraftingManager();
        foreach ($this->newCratingManger->getShapedRecipes() as $shapedRecipes) {
            foreach ($shapedRecipes as $recipe) {
                $craftingManager->registerShapedRecipe($recipe);
            }
        }
    }
}